<?php

declare(strict_types=1);

namespace Antares\Tests\Core\Lifecycle;

use Antares\Application;
use Antares\Container\Container;
use Antares\Exceptions\HttpException;
use Antares\Middleware\MiddlewareInterface;
use Antares\Router\Attributes\Get;
use Antares\Router\Router;
use Antares\ServiceProvider;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One booted Application handling several requests, as it does under a worker
 * runtime (FrankenPHP, Swoole).
 */
class MiddlewareRequestLifecycleTest extends TestCase
{
    private function app(array $middleware): Application
    {
        $backup = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false', 'APP_DEBUG' => 'false']);
            $app = Application::create(sys_get_temp_dir())
                ->providers([ScopedContextProvider::class])
                ->routeProviders([LifecycleRoutes::class])
                ->middleware($middleware);
            $app->boot();
        } finally {
            $_ENV = $backup;
        }

        return $app;
    }

    public function test_middleware_and_controller_share_a_fresh_scoped_instance_per_request(): void
    {
        $app = $this->app([SetsUserMiddleware::class]);

        foreach (['alice', 'bob', 'carol'] as $user) {
            $request  = (new ServerRequest('GET', '/whoami'))->withHeader('X-User', $user);
            $response = $app->handle($request);

            // The controller sees what this request's middleware set, not a previous one.
            $this->assertSame($user, json_decode((string) $response->getBody(), true)['user']);
            // The middleware got an empty instance, not one left over from the last request.
            $this->assertSame('', $response->getHeaderLine('X-Previous-User'));
        }
    }

    public function test_outer_middleware_sees_error_thrown_by_inner_middleware(): void
    {
        $app = $this->app([MarksResponseMiddleware::class, RateLimitedMiddleware::class]);

        $response = $app->handle(new ServerRequest('GET', '/whoami'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('yes', $response->getHeaderLine('X-Outer'));
    }
}

final class RequestContext
{
    public string $user = '';
}

final class ScopedContextProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->scoped(RequestContext::class, fn () => new RequestContext());
    }
}

final class SetsUserMiddleware implements MiddlewareInterface
{
    public function __construct(private RequestContext $context) {}

    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $previous = $this->context->user;
        $this->context->user = $request->getHeaderLine('X-User');

        return $next($request)->withHeader('X-Previous-User', $previous);
    }
}

final class MarksResponseMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request)->withHeader('X-Outer', 'yes');
    }
}

final class RateLimitedMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        throw new HttpException(429, 'Too Many Requests');
    }
}

final class LifecycleController
{
    public function __construct(private RequestContext $context) {}

    #[Get('/whoami')]
    public function whoami(): array
    {
        return ['user' => $this->context->user];
    }
}

final class LifecycleRoutes implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->make(Router::class)->register(LifecycleController::class);
    }
}
