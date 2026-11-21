<?php

declare(strict_types=1);

namespace Antares\Tests\Core\Errors;

use Antares\Application;
use Antares\ErrorHandler;
use Antares\Exceptions\HttpException;
use Antares\Exceptions\HttpExceptionInterface;
use Antares\Hydration\Exceptions\HydrationException;
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Router;
use Antares\ServiceProvider;
use Antares\Container\Container;
use Antares\Validation\Exceptions\ValidationException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class ErrorHandlerTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        RecordingLoggerProvider::$logger = $this->logger;
    }

    private function handler(bool $debug = false, array $map = []): ErrorHandler
    {
        return new ErrorHandler(debug: $debug, logger: $this->logger, exceptionMap: $map, basePath: '/app');
    }

    private function body(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    // Safe exceptions: real status + message, in every environment

    /** @return array<string, array{\Throwable, int, string}> */
    public static function safeExceptions(): array
    {
        return [
            'validation'   => [new ValidationException(['email' => ['Invalid email']]), 422, 'Validation failed'],
            'hydration'    => [new HydrationException('Missing required parameter: email'), 400, 'Missing required parameter: email'],
            'http'         => [new HttpException(409, 'Email already in use'), 409, 'Email already in use'],
            'http 5xx'     => [new HttpException(503, 'Down for maintenance'), 503, 'Down for maintenance'],
            'own safe one' => [new EmailTaken(), 409, 'Email already in use'],
        ];
    }

    #[DataProvider('safeExceptions')]
    public function test_safe_exceptions_show_their_message_even_without_debug(\Throwable $e, int $status, string $title): void
    {
        $response = $this->handler(debug: false)->handle($e);
        $body = $this->body($response);

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($title, $body['title']);
        $this->assertSame($status, $body['status']);
        $this->assertArrayNotHasKey('debug', $body);
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_validation_errors_are_included(): void
    {
        $body = $this->body($this->handler()->handle(new ValidationException(['email' => ['Invalid email']])));
        $this->assertSame(['email' => ['Invalid email']], $body['errors']);
    }

    // Unexpected exceptions: hidden unless debug

    public function test_unexpected_exception_is_hidden_without_debug(): void
    {
        $response = $this->handler(debug: false)->handle(new \PDOException('SQLSTATE[42S02]: Base table users not found'));
        $body = $this->body($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['type' => 'https://antares.dev/errors', 'title' => 'Internal Server Error', 'status' => 500], $body);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
    }

    public function test_unexpected_exception_shows_details_with_debug(): void
    {
        $e = new \RuntimeException('Something broke', 0, new \LogicException('Root cause'));
        $body = $this->body($this->handler(debug: true)->handle($e));

        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertSame('Something broke', $body['debug']['message']);
        $this->assertSame(\RuntimeException::class, $body['debug']['exception']);
        $this->assertStringContainsString('ErrorHandlerTest.php:', $body['debug']['file']);
        $this->assertIsArray($body['debug']['trace']);
        $this->assertSame('Root cause', $body['debug']['previous']['message']);
    }

    public function test_http_exception_with_an_invalid_status_is_treated_as_unexpected(): void
    {
        $response = $this->handler(debug: false)->handle(new HttpException(0, 'Oops'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $this->body($response)['title']);
    }

    // Logging

    public function test_5xx_errors_are_logged_with_the_exception(): void
    {
        $e = new \RuntimeException('Something broke');
        $this->handler()->handle($e);

        $this->assertCount(1, $this->logger->records);
        [$level, $message, $context] = $this->logger->records[0];
        $this->assertSame('error', $level);
        $this->assertStringContainsString('RuntimeException: Something broke', $message);
        $this->assertSame($e, $context['exception']);
        $this->assertSame(500, $context['status']);
    }

    public function test_4xx_errors_are_not_logged(): void
    {
        $this->handler()->handle(new HttpException(404, 'User not found'));
        $this->handler()->handle(new ValidationException(['x' => ['y']]));
        $this->assertSame([], $this->logger->records);
    }

    // Exception map

    public function test_mapped_exception_keeps_its_message(): void
    {
        $response = $this->handler(map: [ModelNotFound::class => 404])->handle(new ModelNotFound('User 7 not found'));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('User 7 not found', $this->body($response)['title']);
    }

    public function test_mapped_exception_can_replace_its_message(): void
    {
        $response = $this->handler(map: [ModelNotFound::class => [409, 'Conflict']])->handle(new ModelNotFound('internal detail'));
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Conflict', $this->body($response)['title']);
    }

    public function test_map_matches_subclasses(): void
    {
        $response = $this->handler(map: [\DomainException::class => 400])->handle(new ModelNotFound('Nope'));
        $this->assertSame(400, $response->getStatusCode());
    }

    // Routing through a real app: 404 / 405

    private function app(array $env = []): Application
    {
        $backup = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false'], $env);
            $app = Application::create(sys_get_temp_dir())
                ->providers([RecordingLoggerProvider::class])
                ->routeProviders([ErrorRoutes::class]);
            $app->boot();
            return $app;
        } finally {
            $_ENV = $backup;
        }
    }

    public function test_unknown_route_returns_404(): void
    {
        $response = $this->app()->handle(new ServerRequest('GET', '/nope'));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Route not found', $this->body($response)['title']);
    }

    public function test_wrong_method_returns_405_with_allow_header(): void
    {
        $response = $this->app()->handle(new ServerRequest('DELETE', '/things'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, POST', $response->getHeaderLine('Allow'));
    }

    public function test_app_debug_false_hides_controller_bugs(): void
    {
        $response = $this->app(['APP_DEBUG' => 'false'])->handle(new ServerRequest('GET', '/broken'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayNotHasKey('debug', $this->body($response));
    }

    public function test_app_debug_true_shows_controller_bugs(): void
    {
        $response = $this->app(['APP_DEBUG' => 'true'])->handle(new ServerRequest('GET', '/broken'));
        $this->assertSame('Database is on fire', $this->body($response)['debug']['message']);
    }

    public function test_app_debug_defaults_to_off_in_production(): void
    {
        $base = sys_get_temp_dir() . '/antares-debug-test-' . bin2hex(random_bytes(4));
        mkdir($base);
        try {
            $response = $this->appIn($base, ['APP_ENV' => 'production'])->handle(new ServerRequest('GET', '/broken'));
            $this->assertArrayNotHasKey('debug', $this->body($response));
        } finally {
            @unlink("$base/storage/cache/routes.php");
            @unlink("$base/storage/cache/fingerprint");
            @rmdir("$base/storage/cache");
            @rmdir("$base/storage");
            @rmdir($base);
        }
    }

    private function appIn(string $base, array $env): Application
    {
        $backup = $_ENV;
        try {
            unset($_ENV['APP_DEBUG'], $_ENV['APP_ENV']);
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false'], $env);
            $app = Application::create($base)
                ->providers([RecordingLoggerProvider::class])
                ->routeProviders([ErrorRoutes::class]);
            $app->boot();
            return $app;
        } finally {
            $_ENV = $backup;
        }
    }

    public function test_app_uses_logger_from_the_container(): void
    {
        $backup = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false']);
            $app = Application::create(sys_get_temp_dir())
                ->providers([RecordingLoggerProvider::class])
                ->routeProviders([ErrorRoutes::class]);
            $app->boot();
        } finally {
            $_ENV = $backup;
        }

        $app->handle(new ServerRequest('GET', '/broken'));
        $this->assertCount(1, $this->logger->records);
    }

    // Middleware sees error responses

    public function test_middleware_runs_on_error_responses(): void
    {
        $backup = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false']);
            $app = Application::create(sys_get_temp_dir())
                ->providers([RecordingLoggerProvider::class])
                ->routeProviders([ErrorRoutes::class])
                ->middleware([HeaderMiddleware::class]);
            $app->boot();
        } finally {
            $_ENV = $backup;
        }

        foreach (['/things' => 200, '/nope' => 404, '/broken' => 500] as $path => $status) {
            $response = $app->handle(new ServerRequest('GET', $path));
            $this->assertSame($status, $response->getStatusCode(), $path);
            $this->assertSame('yes', $response->getHeaderLine('X-Middleware'), "middleware header missing on $path");
        }
    }

    public function test_exception_in_middleware_still_becomes_an_error_response(): void
    {
        $backup = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false', 'APP_DEBUG' => 'false']);
            $app = Application::create(sys_get_temp_dir())
                ->providers([RecordingLoggerProvider::class])
                ->routeProviders([ErrorRoutes::class])
                ->middleware([ThrowingMiddleware::class]);
            $app->boot();
        } finally {
            $_ENV = $backup;
        }

        $response = $app->handle(new ServerRequest('GET', '/things'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $this->body($response)['title']);
    }

    // Application::exceptions() validation

    /** @return array<string, array{array}> */
    public static function invalidMaps(): array
    {
        return [
            'not an exception'     => [[\stdClass::class => 404]],
            'status out of range'  => [[\RuntimeException::class => 200]],
            'message not a string' => [[\RuntimeException::class => [404, 123]]],
        ];
    }

    #[DataProvider('invalidMaps')]
    public function test_exceptions_rejects_invalid_maps(array $map): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Application::create(sys_get_temp_dir())->exceptions($map);
    }
}

final class EmailTaken extends \DomainException implements HttpExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Email already in use');
    }

    public function getStatusCode(): int
    {
        return 409;
    }
}

final class ModelNotFound extends \DomainException {}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string, array}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message, $context];
    }
}

final class RecordingLoggerProvider implements ServiceProvider
{
    public static ?LoggerInterface $logger = null;

    public function register(Container $container): void
    {
        $container->singleton(LoggerInterface::class, fn () => self::$logger);
    }
}

final class ErrorController
{
    #[Get('/things')]
    public function index(): array
    {
        return [];
    }

    #[Post('/things')]
    public function store(): array
    {
        return [];
    }

    #[Get('/broken')]
    public function broken(): array
    {
        throw new \RuntimeException('Database is on fire');
    }
}

final class ErrorRoutes implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->make(Router::class)->register(ErrorController::class);
    }
}

final class HeaderMiddleware implements \Antares\Middleware\MiddlewareInterface
{
    public function handle(\Psr\Http\Message\ServerRequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
    {
        return $next($request)->withHeader('X-Middleware', 'yes');
    }
}

final class ThrowingMiddleware implements \Antares\Middleware\MiddlewareInterface
{
    public function handle(\Psr\Http\Message\ServerRequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
    {
        throw new \RuntimeException('Middleware broke');
    }
}
