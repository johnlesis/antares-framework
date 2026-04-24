# antares-router

Lightweight attribute-based router for PHP 8.2+. Built on top of [nikic/fast-route](https://github.com/nikic/FastRoute). Can be used standalone or as part of the [Antares framework](https://github.com/johnlesis/antares).

## Installation

```bash
composer require fatjon-lleshi/antares-router
```

## Standalone Usage

### Example Project Structure

```
project/
├── public/
│   └── index.php
├── app/
│   └── Controllers/
│       ├── UserController.php
│       └── ProductController.php
├── routes.php
└── composer.json
```

### Defining Routes

Define routes directly on controller methods using PHP attributes:

```php
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Attributes\Put;
use Antares\Router\Attributes\Patch;
use Antares\Router\Attributes\Delete;

class UserController
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
```

### Registering Routes

Create a `routes.php` file that registers all your controllers:

```php
use Antares\Router\Router;

return function(Router $router): void {
    $router->register(UserController::class);
    $router->register(ProductController::class);
    $router->register(OrderController::class);
};
```

### Bootstrapping

In `public/index.php`, create the router, load the routes file, and dispatch the request:

```php
use Antares\Router\Router;

$router = new Router();
$register = require __DIR__ . '/../routes.php';
$register($router);

try {
    [$controllerClass, $method, $routeParams, $statusCode] = $router->match(
        $_SERVER['REQUEST_METHOD'],
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    );

    $controller = new $controllerClass();
    $controller->$method();
} catch (\RuntimeException $e) {
    http_response_code($e->getCode());
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Route Parameters

Parameters in the path are extracted and returned as an array:

```php
[$controller, $method, $params, $status] = $router->match('GET', '/users/42');

echo $params['id']; // "42"
```

### Custom Status Codes

Pass a status code as the second argument to any route attribute. Defaults to `200`:

```php
#[Post('/users', 201)]
public function store(): void {}

#[Delete('/users/{id}', 204)]
public function destroy(): void {}
```

### Array Config

Register routes from a plain array if you prefer config-based routing instead of attributes:

```php
$router->registerFromConfig([
    ['GET',    '/users',      UserController::class, 'index',   200],
    ['POST',   '/users',      UserController::class, 'store',   201],
    ['GET',    '/users/{id}', UserController::class, 'show',    200],
    ['PUT',    '/users/{id}', UserController::class, 'update',  200],
    ['DELETE', '/users/{id}', UserController::class, 'destroy', 204],
]);
```

### YAML Config

Register routes from a YAML file. Requires `symfony/yaml`:

```bash
composer require symfony/yaml
```

```yaml
routes:
  - method: GET
    path: /users
    controller: App\Controllers\UserController
    action: index
    status: 200
  - method: POST
    path: /users
    controller: App\Controllers\UserController
    action: store
    status: 201
```

```php
$router->registerFromYaml(__DIR__ . '/../routes.yaml');
```

### Route Caching

Cache compiled routes for production to avoid reflection overhead on every request:

```php
$cachePath = __DIR__ . '/../storage/cache/routes.php';

if (file_exists($cachePath)) {
    $router->loadFromCache($cachePath);
} else {
    $register = require __DIR__ . '/../routes.php';
    $register($router);
    $router->saveToCache($cachePath);
}
```

### Error Handling

`match()` throws a `RuntimeException` when a route is not found or the method is not allowed:

```php
try {
    [$controller, $method, $params, $status] = $router->match('GET', '/not-found');
} catch (\RuntimeException $e) {
    $e->getCode(); // 404 = not found, 405 = method not allowed
}
```

## Available Attributes

| Attribute   | HTTP Method |
|-------------|-------------|
| `#[Get]`    | GET         |
| `#[Post]`   | POST        |
| `#[Put]`    | PUT         |
| `#[Patch]`  | PATCH       |
| `#[Delete]` | DELETE      |

## Requirements

- PHP 8.2+
- nikic/fast-route ^1.3

## License

MIT