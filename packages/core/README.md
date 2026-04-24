# antares

Lightweight API-focused PHP framework for PHP 8.2+. Built around explicitness, type safety, and contract-first design.

## Installation

```bash
composer require fatjon-lleshi/antares
```

## Quick Start

```php
// public/index.php
Application::create(__DIR__ . '/..')
    ->providers([AppServiceProvider::class])
    ->routeProviders([RouteServiceProvider::class])
    ->middleware([LogMiddleware::class])
    ->run();
```

## Application Boot

The boot sequence runs in this order:

1. `.env` is loaded via `vlucas/phpdotenv`
2. Container is created
3. Bridge packages are auto-discovered via `installed.json`
4. `providers` are registered — container bindings and singletons
5. `routeProviders` are registered — controllers registered with the router
6. Route cache is loaded (production) or built fresh (local)
7. Dispatcher, ErrorHandler, and Pipeline are wired up

---

## Service Providers

Implement `ServiceProvider` to register container bindings and singletons. Use singletons for anything that needs values from `.env` since the container cannot autowire primitives:

```php
use Antares\ServiceProvider;
use Antares\Container\Container;

class AppServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(LoggerInterface::class, FileLogger::class);

        $container->singleton(Mailer::class, fn() => new Mailer(
            host: $_ENV['MAIL_HOST'],
            port: (int) $_ENV['MAIL_PORT'],
            secret: $_ENV['MAIL_SECRET'],
        ));
    }
}
```

---

## Route Providers

Implement `ServiceProvider` to register controllers with the router:

```php
use Antares\ServiceProvider;
use Antares\Container\Container;
use Antares\Router\Router;

class RouteServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $router = $container->make(Router::class);
        $router->register(UserController::class);
        $router->register(PostController::class);
    }
}
```

---

## Controllers

Define routes with PHP attributes. The dispatcher resolves all dependencies automatically:

```php
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Attributes\Delete;

class UserController
{
    #[Get('/users')]
    public function index(): array
    {
        return ['users' => []];
    }

    #[Get('/users/{id}')]
    public function show(int $id): UserResponse
    {
        return new UserResponse(id: $id, firstName: 'John', lastName: 'Doe');
    }

    #[Post('/users', 201)]
    public function store(CreateUserRequest $request): UserResponse
    {
        return new UserResponse(id: 1, firstName: $request->firstName, lastName: $request->lastName);
    }
}
```

### Controller Return Types

The dispatcher handles four return types:

**`#[ResponseDto]` object** — serialized automatically with case conversion and all serialization attributes applied:
```php
#[Post('/users', 201)]
public function store(CreateUserRequest $request): UserResponse
{
    return new UserResponse(id: 1, name: $request->name);
}
```

**`array`** — encoded directly as JSON with no transformation:
```php
#[Get('/health')]
public function health(): array
{
    return ['status' => 'ok', 'version' => '1.0.0'];
}
```

**`null`** — empty response with the route's status code:
```php
#[Delete('/users/{id}', 204)]
public function destroy(int $id): void
{
    // returns 204 No Content
}
```

**`Nyholm\Psr7\Response`** — returned as-is, giving you full control over status code, headers, and body. Implements PSR-7 `ResponseInterface` so it is compatible with any PSR-7 middleware:
```php
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

#[Get('/download/{id}')]
public function download(int $id): ResponseInterface
{
    $content = file_get_contents('/path/to/file');

    return new Response(
        status: 200,
        headers: [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="file.pdf"',
        ],
        body: $content,
    );
}
```

---

## Request DTOs

Mark a class with `#[Dto]` to have it automatically hydrated and validated from the request body. All validation errors are **collected together** — every field is validated and all errors are returned at once, never stopping at the first failure:

```php
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\Email;
use Antares\Validation\Attributes\MinLength;
use Antares\Validation\Attributes\NotBlank;
use Antares\Validation\Attributes\Min;

#[Dto]
readonly class CreateUserRequest
{
    public function __construct(
        #[NotBlank]
        #[MinLength(3)]
        public string $firstName,

        #[NotBlank]
        public string $lastName,

        #[Email]
        public string $email,

        #[Min(18)]
        public int $age,
    ) {}
}
```

If validation fails the response is a `422` with all errors collected:

```json
{
    "type": "https://antares.dev/errors",
    "title": "Validation failed",
    "status": 422,
    "errors": {
        "firstName": ["Must be at least 3 characters"],
        "email": ["Invalid email address"],
        "age": ["Must be at least 18"]
    }
}
```

### Strict Mode

Add `#[Strict]` to reject requests with extra fields not declared in the DTO:

```php
#[Dto]
#[Strict]
readonly class CreateUserRequest
{
    public function __construct(
        public string $firstName,
        public string $email,
    ) {}
}
```

---

## Validation Attributes

Antares ships with a full set of validation attributes:

| Attribute | Description |
|-----------|-------------|
| `#[NotBlank]` | Value must not be empty or whitespace |
| `#[NotNull]` | Value must not be null |
| `#[Email]` | Valid email address |
| `#[Url]` | Valid URL |
| `#[Uuid]` | Valid UUID |
| `#[Ip]` | Valid IP address |
| `#[Phone]` | Valid phone number |
| `#[Date]` | Valid date string (Y-m-d) |
| `#[DateTime]` | Valid datetime string |
| `#[Pattern('/regex/')]` | Matches a regex pattern |
| `#[Min(n)]` | Minimum numeric value |
| `#[Max(n)]` | Maximum numeric value |
| `#[Between(min, max)]` | Numeric value within range |
| `#[Positive]` | Value must be greater than 0 |
| `#[Negative]` | Value must be less than 0 |
| `#[MinLength(n)]` | Minimum string length |
| `#[MaxLength(n)]` | Maximum string length |
| `#[Size(min, max)]` | String length within range |
| `#[Alpha]` | Only alphabetic characters |
| `#[AlphaNumeric]` | Only alphanumeric characters |
| `#[Numeric]` | Only numeric characters |
| `#[HexColor]` | Valid hex color (`#fff` or `#ffffff`) |
| `#[Json]` | Valid JSON string |
| `#[In(['a', 'b'])]` | Value must be in the given list |
| `#[InEnum(StatusEnum::class)]` | Value must be a valid backed enum case |
| `#[ArrayOf('string')]` | Array of a specific type or class |

### Creating Custom Validation Attributes

Implement `ValidationAttribute` to create your own. Return `null` if valid, return an error string if not:

```php
use Antares\Validation\Attributes\ValidationAttribute;
use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Lowercase implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if ($value !== strtolower($value)) {
            return "The value must be lowercase.";
        }

        return null;
    }
}
```

Use it like any built-in attribute:

```php
#[Dto]
readonly class CreateTagRequest
{
    public function __construct(
        #[NotBlank]
        #[Lowercase]
        public string $name,
    ) {}
}
```

---

## Response DTOs

Mark a class with `#[ResponseDto]` to control serialization. The dispatcher detects the attribute automatically and serializes the return value:

```php
use Antares\Serialization\Attributes\ResponseDto;
use Antares\Serialization\Attributes\Hide;
use Antares\Serialization\Attributes\SerializeAs;
use Antares\Serialization\Attributes\Computed;

#[ResponseDto(case: 'snake_case')]
readonly class UserResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        #[Hide]
        public string $passwordHash,
        #[SerializeAs('email')]
        public string $emailAddress,
    ) {}

    #[Computed]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}
```

Output:
```json
{
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "full_name": "John Doe"
}
```

### Serialization Attributes

| Attribute | Target | Description |
|-----------|--------|-------------|
| `#[ResponseDto(case: 'snake_case')]` | Class | Marks class as serializable response, sets output case |
| `#[Hide]` | Property | Excludes property from serialized output |
| `#[SerializeAs('key')]` | Property | Overrides the output key name |
| `#[Computed]` | Method | Includes method return value in output. `get` prefix is stripped — `getFullName()` becomes `full_name` |

### Case Options

| Value | Example |
|-------|---------|
| `snake_case` (default) | `first_name` |
| `camel_case` | `firstName` |
| `pascal_case` | `FirstName` |
| `kebab_case` | `first-name` |

---

## Middleware

Implement `MiddlewareInterface` and pass class strings to `->middleware([])`. Middleware runs globally on every request in the order declared:

```php
use Antares\Middleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class LogMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $start = microtime(true);
        $response = $next($request);
        $elapsed = round((microtime(true) - $start) * 1000);

        return $response->withHeader('X-Response-Time', $elapsed . 'ms');
    }
}
```

---

## Guards

Guards protect individual routes by resolving a value from the request and injecting it into a specific controller parameter. Unlike middleware which runs globally on every request, guards run only on the routes they are applied to. Routes without a `#[Guards]` parameter are fully public.

### Defining a Guard

Implement the `Guard` interface. Throw `HttpException` to reject the request:

```php
use Antares\Http\Guards\Guard;
use Antares\Exceptions\HttpException;
use Psr\Http\Message\ServerRequestInterface;

interface Guard
{
    public function resolve(ServerRequestInterface $request): mixed;
}
```

### JWT Authentication Guard

Resolve the authenticated user from a Bearer token:

```php
class JwtGuard implements Guard
{
    public function __construct(
        private readonly string $secret,
    ) {}

    public function resolve(ServerRequestInterface $request): mixed
    {
        $header = $request->getHeaderLine('Authorization');

        if (empty($header) || !str_starts_with($header, 'Bearer ')) {
            throw new HttpException(401, 'Missing or invalid Authorization header');
        }

        $token = substr($header, 7);
        $payload = $this->decodeToken($token);

        if ($payload === null) {
            throw new HttpException(401, 'Invalid or expired token');
        }

        return new AuthUser(
            id: $payload['sub'],
            email: $payload['email'],
            role: $payload['role'],
        );
    }

    private function decodeToken(string $token): ?array
    {
        // decode and verify JWT against $this->secret
        // return payload array or null if invalid
    }
}
```

Register it as a singleton since it needs `JWT_SECRET` from `.env`:

```php
$container->singleton(JwtGuard::class, fn() => new JwtGuard(
    secret: $_ENV['JWT_SECRET'],
));
```

### API Key Guard

Resolve a client from an API key header:

```php
class ApiKeyGuard implements Guard
{
    public function __construct(
        private readonly ApiClientRepository $clients,
    ) {}

    public function resolve(ServerRequestInterface $request): mixed
    {
        $key = $request->getHeaderLine('X-Api-Key');

        if (empty($key)) {
            throw new HttpException(401, 'Missing API key');
        }

        $client = $this->clients->findByKey($key);

        if ($client === null) {
            throw new HttpException(401, 'Invalid API key');
        }

        return $client;
    }
}
```

### Role-Based Access Guard

Build on top of an existing guard to restrict access by role:

```php
class AdminGuard implements Guard
{
    public function __construct(
        private readonly JwtGuard $jwtGuard,
    ) {}

    public function resolve(ServerRequestInterface $request): mixed
    {
        $user = $this->jwtGuard->resolve($request);

        if ($user->role !== 'admin') {
            throw new HttpException(403, 'Forbidden');
        }

        return $user;
    }
}
```

### Multi-Tenant Guard

Resolve the current tenant and inject it into the controller — useful for multi-tenant APIs where each request belongs to a specific tenant:

```php
class TenantGuard implements Guard
{
    public function __construct(
        private readonly TenantRepository $tenants,
    ) {}

    public function resolve(ServerRequestInterface $request): mixed
    {
        $host = $request->getUri()->getHost();
        $subdomain = explode('.', $host)[0];

        $tenant = $this->tenants->findBySubdomain($subdomain);

        if ($tenant === null) {
            throw new HttpException(404, 'Tenant not found');
        }

        return $tenant;
    }
}
```

### Using Guards on Routes

Apply `#[Guards(GuardClass::class)]` to the parameter that should receive the resolved value. The guard runs before the rest of the parameters are resolved — if it throws, the request is rejected immediately:

```php
use Antares\Http\Attributes\Guards;
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Attributes\Delete;

class PostController
{
    #[Get('/posts')]
    public function index(): array
    {
        return ['posts' => []];
    }

    #[Post('/posts', 201)]
    public function store(
        #[Guards(JwtGuard::class)] AuthUser $user,
        CreatePostRequest $request,
    ): PostResponse {
        return new PostResponse(
            id: 1,
            title: $request->title,
            authorId: $user->id,
        );
    }

    #[Delete('/posts/{id}', 204)]
    public function destroy(
        #[Guards(AdminGuard::class)] AuthUser $user,
        int $id,
    ): void {
        // only admins reach here
    }
}
```

Multiple guards can be used across the same controller — different routes can use different guards:

```php
class ReportController
{
    #[Get('/reports')]
    public function index(
        #[Guards(TenantGuard::class)] Tenant $tenant,
        #[Guards(JwtGuard::class)] AuthUser $user,
    ): array {
        return ['tenant' => $tenant->id, 'user' => $user->id];
    }
}
```

If the guard has no constructor dependencies, the container will autowire it automatically — no registration needed.

---

## ResponseBag

Set response headers from anywhere in the request lifecycle:

```php
use Antares\Http\ResponseBag;

ResponseBag::header('X-Request-Id', uniqid());
ResponseBag::header('X-Rate-Limit-Remaining', '99');
```

Headers are applied to the response automatically and cleared after each request.

---

## Error Handling

All exceptions are caught and converted to RFC 7807 JSON responses automatically:

| Exception | Status Code |
|-----------|-------------|
| `ValidationException` | 422 |
| `HydrationException` | 400 |
| `HttpException($code)` | `$code` |
| Any other `Throwable` | 500 |

Throw `HttpException` anywhere in your code:

```php
use Antares\Exceptions\HttpException;

throw new HttpException(403, 'Forbidden');
throw new HttpException(404, 'User not found');
```

---

## OpenAPI Documentation

Antares auto-generates an OpenAPI 3.0 spec from your controllers, request DTOs, and response DTOs. A `GET /openapi` endpoint is registered automatically on boot — no configuration needed.

The spec is built entirely from your code:

- Route attributes (`#[Get]`, `#[Post]`, etc.) define the paths and methods
- Request DTO validation attributes define the request body schema and constraints — `#[Email]`, `#[Min]`, `#[MaxLength]` etc. are reflected as OpenAPI constraints
- When a controller method returns a `#[ResponseDto]` class, the response schema is auto-generated from its properties and serialization attributes
- `#[Hide]` properties are excluded from both request and response schemas
- `#[SerializeAs]` key overrides are reflected in the response schema

For example, this controller method:

```php
#[Post('/users', 201)]
public function store(CreateUserRequest $request): UserResponse {}
```

Generates a full OpenAPI path entry with the request body schema derived from `CreateUserRequest` validation attributes and the `201` response schema derived from `UserResponse` serialization attributes — with no extra work.

Mark a route as deprecated in the spec with `#[Deprecated]`:

```php
use Antares\OpenApi\Attributes\Deprecated;
use Antares\Router\Attributes\Get;

class UserController
{
    #[Get('/v1/users')]
    #[Deprecated]
    public function indexV1(): array
    {
        return ['users' => []];
    }

    #[Get('/v2/users')]
    public function indexV2(): UserListResponse
    {
        return new UserListResponse(users: []);
    }
}
```

---

## Route Caching

In production, routes are compiled and cached automatically when `APP_ENV=production`. The cache is invalidated automatically when `composer.lock`, `.env`, or any file in `app/` changes:

```env
APP_ENV=production
```

Clear the cache manually:

```bash
php bin/antares cache:clear
```

---

## Auto-Discovery

Bridge packages declare their service providers in `composer.json` and are registered automatically on boot — no manual registration needed:

```json
"extra": {
    "antares": {
        "providers": [
            "Antares\\Monolog\\MonologServiceProvider"
        ]
    }
}
```

---

## CLI

```bash
php bin/antares make:controller UserController
php bin/antares make:dto CreateUserRequest
php bin/antares make:response UserResponse
php bin/antares make:middleware AuthMiddleware
php bin/antares make:guard JwtGuard
php bin/antares cache:clear
```

---

## Full Example

A complete API with all features combined.

**`.env`:**
```env
APP_ENV=local
JWT_SECRET=supersecret
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_SECRET=mailsecret
```

**`public/index.php`:**
```php
Application::create(__DIR__ . '/..')
    ->providers([AppServiceProvider::class])
    ->routeProviders([RouteServiceProvider::class])
    ->middleware([LogMiddleware::class])
    ->run();
```

**`AppServiceProvider.php`:**
```php
class AppServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(JwtGuard::class, fn() => new JwtGuard(
            secret: $_ENV['JWT_SECRET'],
        ));

        $container->singleton(Mailer::class, fn() => new Mailer(
            host: $_ENV['MAIL_HOST'],
            port: (int) $_ENV['MAIL_PORT'],
            secret: $_ENV['MAIL_SECRET'],
        ));
    }
}
```

**`RouteServiceProvider.php`:**
```php
class RouteServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $router = $container->make(Router::class);
        $router->register(PostController::class);
    }
}
```

**`LogMiddleware.php`:**
```php
class LogMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);
        return $response->withHeader('X-Request-Id', uniqid());
    }
}
```

**`JwtGuard.php`:**
```php
class JwtGuard implements Guard
{
    public function __construct(private readonly string $secret) {}

    public function resolve(ServerRequestInterface $request): mixed
    {
        $header = $request->getHeaderLine('Authorization');

        if (empty($header) || !str_starts_with($header, 'Bearer ')) {
            throw new HttpException(401, 'Unauthorized');
        }

        $token = substr($header, 7);
        $payload = $this->decodeToken($token);

        if ($payload === null) {
            throw new HttpException(401, 'Invalid or expired token');
        }

        return new AuthUser(id: $payload['sub'], role: $payload['role']);
    }

    private function decodeToken(string $token): ?array
    {
        // verify token against $this->secret
    }
}
```

**`CreatePostRequest.php`:**
```php
#[Dto]
readonly class CreatePostRequest
{
    public function __construct(
        #[NotBlank]
        #[MinLength(5)]
        #[MaxLength(100)]
        public string $title,

        #[NotBlank]
        #[MinLength(20)]
        public string $body,

        #[In(['draft', 'published'])]
        public string $status,
    ) {}
}
```

**`PostResponse.php`:**
```php
#[ResponseDto(case: 'snake_case')]
readonly class PostResponse
{
    public function __construct(
        public int $id,
        public string $title,
        public string $body,
        public string $status,
        public int $authorId,
        #[Hide]
        public string $internalNotes,
        #[SerializeAs('created')]
        public string $createdAt,
    ) {}

    #[Computed]
    public function getExcerpt(): string
    {
        return substr($this->body, 0, 100) . '...';
    }
}
```

**`PostController.php`:**
```php
class PostController
{
    #[Get('/posts')]
    public function index(): array
    {
        return ['posts' => []];
    }

    #[Get('/v1/posts/{id}')]
    #[Deprecated]
    public function showV1(int $id): array
    {
        return ['id' => $id];
    }

    #[Get('/v2/posts/{id}')]
    public function show(int $id): PostResponse
    {
        return new PostResponse(
            id: $id,
            title: 'Hello World',
            body: 'This is the full body of the post that will be excerpted in the response.',
            status: 'published',
            authorId: 1,
            internalNotes: 'never exposed in response',
            createdAt: '2024-01-15 10:30:00',
        );
    }

    #[Post('/posts', 201)]
    public function store(
        #[Guards(JwtGuard::class)] AuthUser $user,
        CreatePostRequest $request,
    ): PostResponse {
        return new PostResponse(
            id: 1,
            title: $request->title,
            body: $request->body,
            status: $request->status,
            authorId: $user->id,
            internalNotes: '',
            createdAt: date('Y-m-d H:i:s'),
        );
    }

    #[Delete('/posts/{id}', 204)]
    public function destroy(
        #[Guards(AdminGuard::class)] AuthUser $user,
        int $id,
    ): void {
        // only admins reach here — AdminGuard throws 403 for non-admins
    }
}
```

**What happens on `POST /posts` with an invalid body:**
```json
{
    "type": "https://antares.dev/errors",
    "title": "Validation failed",
    "status": 422,
    "errors": {
        "title": ["Must be at least 5 characters"],
        "body": ["Must be at least 20 characters"],
        "status": ["The value must be one of: draft, published"]
    }
}
```

**What `GET /v2/posts/1` returns:**
```json
{
    "id": 1,
    "title": "Hello World",
    "body": "This is the full body of the post that will be excerpted in the response.",
    "status": "published",
    "author_id": 1,
    "created": "2024-01-15 10:30:00",
    "excerpt": "This is the full body of the post that will be excerpted in the response...."
}
```

`GET /posts` — public, no auth needed.
`GET /v1/posts/{id}` — public, marked deprecated in OpenAPI spec.
`GET /v2/posts/{id}` — public, returns serialized `PostResponse`, response schema auto-generated in OpenAPI.
`POST /posts` — requires valid JWT, validates all fields and collects all errors, returns `201`.
`DELETE /posts/{id}` — requires admin role, returns `204`.

---

## Requirements

- PHP 8.2+

## License

MIT