<?php

namespace Antares\Tests\Router;

use Antares\Router\Router;
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Attributes\Put;
use Antares\Router\Attributes\Patch;
use Antares\Router\Attributes\Delete;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RouterTest extends TestCase
{
    public function test_register_adds_routes(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        $this->assertCount(6, $router->getRoutes());
    }

    public function test_match_get_route(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        [$controller, $method, $params, $status] = $router->match('GET', '/users');
        $this->assertSame(TestController::class, $controller);
        $this->assertSame('index', $method);
        $this->assertSame([], $params);
        $this->assertSame(200, $status);
    }

    public function test_match_post_route_with_custom_status(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        [$controller, $method, $params, $status] = $router->match('POST', '/users');
        $this->assertSame(TestController::class, $controller);
        $this->assertSame('store', $method);
        $this->assertSame(201, $status);
    }

    public function test_match_route_with_param(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        [$controller, $method, $params, $status] = $router->match('GET', '/users/42');
        $this->assertSame(TestController::class, $controller);
        $this->assertSame('show', $method);
        $this->assertSame(['id' => '42'], $params);
        $this->assertSame(200, $status);
    }

    public function test_match_delete_route_with_custom_status(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        [$controller, $method, $params, $status] = $router->match('DELETE', '/users/1');
        $this->assertSame(TestController::class, $controller);
        $this->assertSame('destroy', $method);
        $this->assertSame(204, $status);
    }

    public function test_match_not_found_throws(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        try {
            $router->match('GET', '/not-found');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function test_match_method_not_allowed_throws(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        try {
            $router->match('POST', '/users/1');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame(405, $e->getCode());
        }
    }

    public function test_register_from_config(): void
    {
        $router = new Router();
        $router->registerFromConfig([
            ['GET', '/products', TestController::class, 'index', 200],
        ]);
        $this->assertCount(1, $router->getRoutes());
        [$controller, $method, $params, $status] = $router->match('GET', '/products');
        $this->assertSame(TestController::class, $controller);
        $this->assertSame('index', $method);
    }

    public function test_save_and_load_cache(): void
    {
        $router = new Router();
        $router->register(TestController::class);
        $cachePath = sys_get_temp_dir() . '/antares_router_test_cache.php';
        $router->saveToCache($cachePath);
        $router2 = new Router();
        $router2->loadFromCache($cachePath);
        $this->assertCount(6, $router2->getRoutes());
        [$controller, $method, $params, $status] = $router2->match('GET', '/users');
        $this->assertSame(TestController::class, $controller);
        unlink($cachePath);
    }
}

class TestController
{
    #[Get('/users')]
    public function index(): void {}

    #[Get('/users/{id}')]
    public function show(): void {}

    #[Post('/users', 201)]
    public function store(): void {}

    #[Put('/users/{id}')]
    public function update(): void {}

    #[Patch('/users/{id}')]
    public function edit(): void {}

    #[Delete('/users/{id}', 204)]
    public function destroy(): void {}
}