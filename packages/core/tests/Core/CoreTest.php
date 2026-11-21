<?php

declare(strict_types=1);

namespace Antares\Tests\Core;

use Antares\Support\CaseConverter;
use Antares\Serialization\CaseStyle;
use Antares\Serialization\Serializer;
use Antares\Serialization\Attributes\ResponseDto;
use Antares\Serialization\Attributes\Hide;
use Antares\Serialization\Attributes\SerializeAs;
use Antares\Serialization\Attributes\Computed;
use Antares\Http\ResponseBag;
use Antares\Container\Container;
use Antares\Middleware\Pipeline;
use Antares\Middleware\MiddlewareInterface;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;

class CoreTest extends TestCase
{
    // CaseConverter

    public function test_case_converter_snake_case(): void
    {
        $this->assertSame('first_name', CaseConverter::convert('firstName', CaseStyle::SnakeCase));
        $this->assertSame('created_at', CaseConverter::convert('createdAt', CaseStyle::SnakeCase));
    }

    public function test_case_converter_pascal_case(): void
    {
        $this->assertSame('FirstName', CaseConverter::convert('firstName', CaseStyle::PascalCase));
    }

    public function test_case_converter_kebab_case(): void
    {
        $this->assertSame('first-name', CaseConverter::convert('firstName', CaseStyle::KebabCase));
        $this->assertSame('created-at', CaseConverter::convert('createdAt', CaseStyle::KebabCase));
    }

    public function test_case_converter_default_returns_unchanged(): void
    {
        $this->assertSame('firstName', CaseConverter::convert('firstName', CaseStyle::CamelCase));
    }

    // Serializer

    public function test_serializer_snake_case(): void
    {
        $dto = new SnakeCaseDto('John', 'john@example.com');
        $result = (new Serializer())->serialize($dto);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('email_address', $result);
        $this->assertSame('John', $result['first_name']);
    }

    public function test_serializer_hides_property(): void
    {
        $dto = new HiddenFieldDto('John', 'secret');
        $result = (new Serializer())->serialize($dto);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('password', $result);
    }

    public function test_serializer_serialize_as(): void
    {
        $dto = new SerializeAsDto('John');
        $result = (new Serializer())->serialize($dto);
        $this->assertArrayHasKey('full_name', $result);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function test_serializer_computed(): void
    {
        $dto = new ComputedDto('John', 'Doe');
        $result = (new Serializer())->serialize($dto);
        $this->assertArrayHasKey('full_name', $result);
        $this->assertSame('John Doe', $result['full_name']);
    }

    // ResponseBag

    public function test_response_bag_set_and_get_header(): void
    {
        ResponseBag::clear();
        ResponseBag::header('X-Request-Id', 'abc123');
        $this->assertSame(['X-Request-Id' => 'abc123'], ResponseBag::getHeaders());
    }

    public function test_response_bag_clear(): void
    {
        ResponseBag::header('X-Foo', 'bar');
        ResponseBag::clear();
        $this->assertSame([], ResponseBag::getHeaders());
    }

    // Pipeline

    public function test_pipeline_calls_destination_with_no_middleware(): void
    {
        $request = new ServerRequest('GET', '/');
        $called = false;

        $pipeline = new Pipeline(new Container(), [], function (ServerRequestInterface $req) use (&$called) {
            $called = true;
            return new Response(200);
        });

        $response = $pipeline->run($request);
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_pipeline_runs_middleware_in_order(): void
    {
        $request = new ServerRequest('GET', '/');
        MiddlewareOrder::$log = [];

        $pipeline = new Pipeline(
            new Container(),
            [FirstMiddleware::class, SecondMiddleware::class],
            function (ServerRequestInterface $req) {
                MiddlewareOrder::$log[] = 'destination';
                return new Response(200);
            }
        );

        $pipeline->run($request);
        $this->assertSame(['first', 'second', 'destination'], MiddlewareOrder::$log);
    }

    public function test_pipeline_resolves_middleware_dependencies_from_container(): void
    {
        $container = new Container();
        $container->singleton(MiddlewareGreeting::class, fn() => new MiddlewareGreeting('hello'));

        $pipeline = new Pipeline(
            $container,
            [InjectedMiddleware::class],
            fn(ServerRequestInterface $req) => new Response(200),
        );

        $response = $pipeline->run(new ServerRequest('GET', '/'));
        $this->assertSame('hello', $response->getHeaderLine('X-Greeting'));
    }
}

// DTOs for Serializer tests

#[ResponseDto(case: CaseStyle::SnakeCase)]
readonly class SnakeCaseDto
{
    public function __construct(
        public string $firstName,
        public string $emailAddress,
    ) {}
}

#[ResponseDto(case: CaseStyle::SnakeCase)]
readonly class HiddenFieldDto
{
    public function __construct(
        public string $name,
        #[Hide]
        public string $password,
    ) {}
}

#[ResponseDto(case: CaseStyle::SnakeCase)]
readonly class SerializeAsDto
{
    public function __construct(
        #[SerializeAs('full_name')]
        public string $name,
    ) {}
}

#[ResponseDto(case: CaseStyle::SnakeCase)]
readonly class ComputedDto
{
    public function __construct(
        public string $firstName,
        public string $lastName,
    ) {}

    #[Computed]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}

// Middleware for Pipeline tests

class MiddlewareOrder
{
    public static array $log = [];
}

final class MiddlewareGreeting
{
    public function __construct(public string $text) {}
}

class InjectedMiddleware implements MiddlewareInterface
{
    public function __construct(private MiddlewareGreeting $greeting) {}

    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request)->withHeader('X-Greeting', $this->greeting->text);
    }
}

class FirstMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        MiddlewareOrder::$log[] = 'first';
        return $next($request);
    }
}

class SecondMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        MiddlewareOrder::$log[] = 'second';
        return $next($request);
    }
}