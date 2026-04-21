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

    public static function create(string $basePath): static
    {
        $app = new static();
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

    public function run(): void
    {
        $this->boot();

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator($factory, $factory, $factory, $factory);
        $request = $creator->fromGlobals();

        $response = $this->handle($request);
        $this->emit($response);
    }

    public function boot(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();
        
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

            $router->register(\Antares\OpenApi\OpenApiController::class);

            if ($useCache) {
                $router->saveToCache($cachePath);
                file_put_contents($fingerprintPath, $currentFingerprint);
            }
        }

        $this->dispatcher = new Dispatcher($this->container, $router, new Hydrator(new Validator()), new Serializer());
        $this->errorHandler = new ErrorHandler();
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
        $pipeline = new Pipeline(
            $this->middleware,
            fn($request) => $this->dispatcher->dispatch($request),
        );

        try {
            return $pipeline->run($request);
        } catch (\Throwable $e) {
            return $this->errorHandler->handle($e);
        }
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
