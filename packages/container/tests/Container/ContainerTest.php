<?php

namespace Antares\Tests\Hydration;

use Antares\Container\Container;
use Antares\Container\ContainerException;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function test_make_class_with_no_constructor(): void
    {
        $container = new Container();
        $result = $container->make(NoConstructor::class);
        $this->assertInstanceOf(NoConstructor::class, $result);
    }

    public function test_bind_interface_to_concrete(): void
    {
        $container = new Container();
        $container->bind(LoggerInterface::class, FileLogger::class);
        $result = $container->make(LoggerInterface::class);
        $this->assertInstanceOf(FileLogger::class, $result);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $container = new Container();
        $container->singleton(FileLogger::class, fn() => new FileLogger());
        $first = $container->make(FileLogger::class);
        $second = $container->make(FileLogger::class);
        $this->assertSame($first, $second);
    }

    public function test_autowiring_resolves_dependencies(): void
    {
        $container = new Container();
        $result = $container->make(UserService::class);
        $this->assertInstanceOf(UserService::class, $result);
        $this->assertInstanceOf(FileLogger::class, $result->logger);
    }

    public function test_nested_autowiring(): void
    {
        $container = new Container();
        $result = $container->make(NestedService::class);
        $this->assertInstanceOf(NestedService::class, $result);
        $this->assertInstanceOf(UserService::class, $result->userService);
        $this->assertInstanceOf(FileLogger::class, $result->userService->logger);
    }

    public function test_circular_dependency_throws(): void
    {
        $container = new Container();

        try {
            $container->make(CircularA::class);
            $this->fail('Expected ContainerException');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('Circular dependency', $e->getMessage());
        }
    }

    public function test_unbound_interface_throws(): void
    {
        $container = new Container();

        try {
            $container->make(LoggerInterface::class);
            $this->fail('Expected ContainerException');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('Cannot instantiate', $e->getMessage());
        }
    }

    public function test_unresolvable_primitive_throws(): void
    {
        $container = new Container();

        try {
            $container->make(PrimitiveService::class);
            $this->fail('Expected ContainerException');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('Cannot resolve primitive', $e->getMessage());
        }
    }
}

class NoConstructor {}

interface LoggerInterface {}

class FileLogger implements LoggerInterface {}

class UserService {
    public function __construct(
        public readonly FileLogger $logger
    ) {}
}

class NestedService {
    public function __construct(
        public readonly UserService $userService
    ) {}
}

class CircularA {
    public function __construct(
        public readonly CircularB $b
    ) {}
}

class CircularB {
    public function __construct(
        public readonly CircularA $a
    ) {}
}

class PrimitiveService {
    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {}
}