<?php

declare(strict_types=1);

namespace Antares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Antares\Container\Container;
use Antares\Hydration\Hydrator;
use Antares\Middleware\Pipeline;
use Antares\OpenApi\Generator;
use Antares\Router\Router;
use Antares\Serialization\Serializer;
use Antares\Validation\Validator;


class Application
{
    private array $providers = [];
    private array $routeProviders = [];
    private string $basePath;
    private Container $container;
    private Dispatcher $dispatcher;
    private ErrorHandler $errorHandler;
    private array $middleware = [];
    /** @var array<class-string<\Throwable>, int|array{0: int, 1: string}> */
    private array $exceptionMap = [];

    public static function create(string $basePath): self
    {
        $app = new self();
        $app->basePath = $basePath;
        return $app;
    }

    public function providers(array $providers): static
    {
        $this->providers = $providers;
        return $this;
    }

    public function routeProviders(array $providers): static
    {
        $this->routeProviders = $providers;
        return $this;
    }

    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Show these exceptions to the client instead of hiding them behind a 500.
     * Use it for exceptions you can't change, e.g. from libraries:
     *
     *     ->exceptions([
     *         ModelNotFoundException::class => 404,                 // keeps the exception's message
     *         QueryException::class         => [409, 'Conflict'],   // replaces it
     *     ])
     *
     * Subclasses match too; the first matching entry wins. The map is checked here, so
     * a typo fails on boot instead of when the exception is thrown.
     *
     * @param array<mixed> $map class-string<\Throwable> => int|array{0: int, 1: string}
     */
    public function exceptions(array $map): static
    {
        foreach ($map as $class => $mapping) {
            if (!is_string($class) || !is_a($class, \Throwable::class, true)) {
                throw new \InvalidArgumentException("exceptions(): '{$class}' is not an exception class.");
            }

            $status = is_array($mapping) ? ($mapping[0] ?? null) : $mapping;
            $valid  = is_int($status) && $status >= 400 && $status <= 599
                && (!is_array($mapping) || (count($mapping) === 2 && is_string($mapping[1] ?? null)));

            if (!$valid) {
                throw new \InvalidArgumentException(
                    "exceptions(): {$class} must map to a status code (400-599) or [status, message]."
                );
            }
        }

        $this->exceptionMap = $map;
        return $this;
    }

    public function run(): void
    {
        $this->boot();

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator($factory, $factory, $factory, $factory);
        $request = $creator->fromGlobals();

        $response = $this->handle($request);
        $this->emit($response);
    }

    public function runWorker(): void
    {
        if (!function_exists('frankenphp_handle_request')) {
            throw new \RuntimeException('runWorker() requires FrankenPHP runtime.');
        }

        $this->boot();

        // Keep the worker alive if a client disconnects mid-response
        ignore_user_abort(true);

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator($factory, $factory, $factory, $factory);

        $handler = function () use ($creator): void {
            $this->emit($this->handle($creator->fromGlobals()));
        };

        // The app stays booted: one worker handles requests until FrankenPHP stops it,
        // or until MAX_REQUESTS (if set) to guard against memory leaks.
        $maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? $_ENV['MAX_REQUESTS'] ?? 0);

        for ($handled = 0; $maxRequests === 0 || $handled < $maxRequests; $handled++) {
            $keepRunning = \frankenphp_handle_request($handler);
            gc_collect_cycles();

            if (!$keepRunning) {
                break;
            }
        }
    }

    public function runSwoole(string $host = '0.0.0.0', int $port = 8000): void
    {
        $this->boot();

        if (!class_exists(\Swoole\Http\Server::class)) {
            throw new \RuntimeException('runSwoole() requires the Swoole extension.');
        }

        if (!class_exists(\Ilex\SwoolePsr7\SwooleServerRequestConverter::class)) {
            throw new \RuntimeException('runSwoole() requires ilexn/swoole-psr7. Run: composer require ilexn/swoole-psr7');
        }

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $converter = new \Ilex\SwoolePsr7\SwooleServerRequestConverter($factory, $factory, $factory, $factory);

        $server = new \Swoole\Http\Server($host, $port);

        $server->on('request', function(
            \Swoole\Http\Request $swooleRequest,
            \Swoole\Http\Response $swooleResponse,
        ) use ($converter): void {
            $request = $converter->createFromSwoole($swooleRequest);
            $response = $this->handle($request);

            $swooleResponse->status($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                $swooleResponse->header($name, implode(', ', $values));
            }
            $swooleResponse->end((string) $response->getBody());
        });

        $server->start();
    }

    public function boot(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();

        // The framework reads $_ENV, which PHP usually leaves empty (variables_order =
        // "GPCS"), and Dotenv skips variables that already exist. Copy the real
        // environment in, so APP_ENV=production set by Docker, systemd, etc. is seen,
        // and wins over .env.
        $_ENV = getenv() + $_ENV;
        
        $_ENV['APP_BASE_PATH'] = $this->basePath;
        $this->container = new Container();
        $this->container->singleton(Router::class, fn() => new Router());
        $this->container->singleton(
            Generator::class,
            fn() => new Generator($this->container->make(Router::class))
        );

        foreach ($this->discoverProviders() as $providerClass) {
            $provider = new $providerClass();
            if (!$provider instanceof ServiceProvider) {
                continue;
            }
            $provider->register($this->container);
        }

        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass();
            if (!$provider instanceof ServiceProvider) {
                throw new \RuntimeException("{$providerClass} must implement ServiceProvider");
            }
            $provider->register($this->container);
        }

        $router    = $this->container->make(Router::class);
        $cachePath = $this->basePath . '/storage/cache/routes.php';
        $fingerprintPath = $this->basePath . '/storage/cache/fingerprint';
        $useCache  = ($_ENV['APP_ENV'] ?? 'local') === 'production';

        $currentFingerprint = $this->generateFingerprint();
        $cachedFingerprint  = file_exists($fingerprintPath) ? file_get_contents($fingerprintPath) : null;
        $fingerprintMatch   = $currentFingerprint === $cachedFingerprint;

        if ($useCache && file_exists($cachePath) && $fingerprintMatch) {
            $router->loadFromCache($cachePath);
        } else {
            foreach ($this->routeProviders as $providerClass) {
                $provider = new $providerClass();
                if (!$provider instanceof ServiceProvider) {
                    throw new \RuntimeException("{$providerClass} must implement ServiceProvider");
                }

                $provider->register($this->container);
            }

            if ($this->docsEnabled()) {
                $router->register(\Antares\OpenApi\OpenApiController::class);
            }

            if ($useCache) {
                $router->saveToCache($cachePath);
                file_put_contents($fingerprintPath, $currentFingerprint);
            }
        }

        $this->dispatcher = new Dispatcher($this->container, $router, new Hydrator(new Validator()), new Serializer());
        $this->errorHandler = new ErrorHandler(
            debug: $this->debugEnabled(),
            logger: $this->resolveLogger(),
            exceptionMap: $this->exceptionMap,
            basePath: $this->basePath,
        );
    }

    private function emit(\Psr\Http\Message\ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("{$name}: {$value}", false);
            }
        }
        echo $response->getBody();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Errors from routing, guards, DTOs and controllers become a response *inside*
        // the pipeline, so middleware still runs on the way out (e.g. CORS headers on
        // a 404 or 422). Errors thrown by middleware itself are caught by the outer try.
        $pipeline = new Pipeline(
            $this->container,
            $this->middleware,
            function (ServerRequestInterface $request): ResponseInterface {
                try {
                    return $this->dispatcher->dispatch($request);
                } catch (\Throwable $e) {
                    return $this->errorHandler->handle($e);
                }
            },
        );

        try {
            return $pipeline->run($request);
        } catch (\Throwable $e) {
            return $this->errorHandler->handle($e);
        }
    }

    /**
     * /openapi.json and /docs are on by default, except when APP_ENV=production.
     * APP_DOCS=true|false overrides that in any environment.
     */
    private function docsEnabled(): bool
    {
        return $this->envFlag('APP_DOCS');
    }

    /**
     * APP_DEBUG adds the real error to 500 responses. On by default, except when
     * APP_ENV=production.
     */
    private function debugEnabled(): bool
    {
        return $this->envFlag('APP_DEBUG');
    }

    /**
     * Reads a true/false env flag. When it's missing or not a boolean, the flag is
     * on everywhere except APP_ENV=production.
     */
    private function envFlag(string $name): bool
    {
        $flag = $_ENV[$name] ?? null;
        if (is_string($flag) && $flag !== '') {
            $enabled = filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabled !== null) {
                return $enabled;
            }
        }

        return ($_ENV['APP_ENV'] ?? 'local') !== 'production';
    }

    /**
     * The app's PSR-3 logger, if one is registered (e.g. by the Monolog bridge).
     */
    private function resolveLogger(): ?\Psr\Log\LoggerInterface
    {
        try {
            $logger = $this->container->make(\Psr\Log\LoggerInterface::class);
        } catch (\Antares\Container\ContainerException) {
            return null;
        }

        return $logger instanceof \Psr\Log\LoggerInterface ? $logger : null;
    }

    private function generateFingerprint(): string
    {
        $composerLock = $this->basePath . '/composer.lock';
        $env          = $this->basePath . '/.env';
        $appDir       = $this->basePath . '/app';

        $hash = '';

        if (file_exists($composerLock)) {
            $hash .= md5_file($composerLock);
        }

        if (file_exists($env)) {
            $hash .= md5_file($env);
        }

        if (is_dir($appDir)) {
            $hash .= $this->hashDirectory($appDir);
        }

        return md5($hash);
    }

    private function hashDirectory(string $dir): string
    {
        $hash  = '';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $hash .= md5_file($file->getPathname());
            }
        }

        return md5($hash);
    }

    private function discoverProviders(): array
    {
        $installedPath = $this->basePath . '/vendor/composer/installed.json';

        if (!file_exists($installedPath)) {
            return [];
        }

        $installed = json_decode(file_get_contents($installedPath), true);
        $packages  = $installed['packages'] ?? $installed;
        $providers = [];

        foreach ($packages as $package) {
            $discovered = $package['extra']['antares']['providers'] ?? [];
            foreach ($discovered as $provider) {
                if (!in_array($provider, $this->providers, strict: true)) {
                    $providers[] = $provider;
                }
            }
        }

        return $providers;
    }
}
