# antares-container

Lightweight autowiring DI container for PHP 8.2+. Zero dependencies. Can be used standalone or as part of the [Antares framework](https://github.com/johnlesis/antares).

## Installation

```bash
composer require fatjon-lleshi/antares-container
```

## Standalone Usage

### Autowiring

The container resolves classes and their dependencies automatically via reflection. No configuration needed for concrete classes.

```php
use Antares\Container\Container;

class Logger {}

class UserService {
    public function __construct(
        private readonly Logger $logger
    ) {}
}

$container = new Container();
$service = $container->make(UserService::class);
```

### Binding Interfaces

Bind an interface to a concrete implementation:

```php
interface LoggerInterface {}
class FileLogger implements LoggerInterface {}

$container = new Container();
$container->bind(LoggerInterface::class, FileLogger::class);

$logger = $container->make(LoggerInterface::class);
```

### Singletons

Register a class as a singleton — the same instance is returned on every `make()` call. Use this for classes that require primitive constructor arguments (like config values from `.env`):

```php
$container->singleton(Database::class, function(Container $container) {
    return new Database(
        host: $_ENV['DB_HOST'],
        port: (int) $_ENV['DB_PORT'],
        name: $_ENV['DB_NAME'],
    );
});

$db = $container->make(Database::class);
```

### Nested Autowiring

The container recursively resolves nested dependencies:

```php
class Cache {}

class Repository {
    public function __construct(
        private readonly Database $db,
        private readonly Cache $cache,
    ) {}
}

$repository = $container->make(Repository::class);
```

## Error Handling

### Unbound Interface

If you try to resolve an interface without binding it, a `ContainerException` is thrown:

```php
use Antares\Container\ContainerException;

try {
    $container->make(LoggerInterface::class);
} catch (ContainerException $e) {
    echo $e->getMessage();
}
```

### Circular Dependency

Circular dependencies are detected and throw a `ContainerException`:

```php
class A {
    public function __construct(public B $b) {}
}
class B {
    public function __construct(public A $a) {}
}

$container->make(A::class); // throws ContainerException: Circular dependency detected
```

### Unresolvable Primitive

If a class constructor has primitive type hints (`string`, `int`, etc.) with no default values, the container cannot autowire them. Register the class as a singleton instead:

```php
class Mailer {
    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {}
}

$container->singleton(Mailer::class, fn() => new Mailer(
    host: $_ENV['MAIL_HOST'],
    port: (int) $_ENV['MAIL_PORT'],
));
```

## Requirements

- PHP 8.2+

## License

MIT