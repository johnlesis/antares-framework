<?php

declare(strict_types=1);

namespace Antares\Tests\Core;

use Antares\Support\CaseConverter;
use Antares\Serialization\Serializer;
use Antares\Serialization\Attributes\ResponseDto;
use Antares\Serialization\Attributes\Hide;
use Antares\Serialization\Attributes\SerializeAs;
use Antares\Serialization\Attributes\Computed;
use Antares\ErrorHandler;
use Antares\Exceptions\HttpException;
use Antares\Hydration\Exceptions\HydrationException;
use Antares\Validation\Exceptions\ValidationException;
use Antares\Http\ResponseBag;
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
        $this->assertSame('first_name', CaseConverter::convert('firstName', 'snake_case'));
        $this->assertSame('created_at', CaseConverter::convert('createdAt', 'snake_case'));
    }

    public function test_case_converter_pascal_case(): void
    {
        $this->assertSame('FirstName', CaseConverter::convert('firstName', 'pascal_case'));
    }

    public function test_case_converter_kebab_case(): void
    {
        $this->assertSame('first-name', CaseConverter::convert('firstName', 'kebab_case'));
        $this->assertSame('created-at', CaseConverter::convert('createdAt', 'kebab_case'));
    }

    public function test_case_converter_default_returns_unchanged(): void
    {
        $this->assertSame('firstName', CaseConverter::convert('firstName', 'camel_case'));
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

    // ErrorHandler

    public function test_error_handler_validation_exception_returns_422(): void
    {
        $handler = new ErrorHandler();
        $response = $handler->handle(new ValidationException(['email' => ['Invalid email']]));
        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(422, $body['status']);
        $this->assertArrayHasKey('errors', $body);
    }

    public function test_error_handler_hydration_exception_returns_400(): void
    {
        $handler = new ErrorHandler();
        $response = $handler->handle(new HydrationException('Bad input'));
        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $body['status']);
    }

    public function test_error_handler_http_exception_returns_correct_code(): void
    {
        $handler = new ErrorHandler();
        $response = $handler->handle(new HttpException(404, 'Not found'));
        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(404, $body['status']);
    }

    public function test_error_handler_generic_exception_returns_500(): void
    {
        $handler = new ErrorHandler();
        $response = $handler->handle(new \RuntimeException('Something went wrong'));
        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(500, $body['status']);
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

        $pipeline = new Pipeline([], function (ServerRequestInterface $req) use (&$called) {
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
            [FirstMiddleware::class, SecondMiddleware::class],
            function (ServerRequestInterface $req) {
                MiddlewareOrder::$log[] = 'destination';
                return new Response(200);
            }
        );

        $pipeline->run($request);
        $this->assertSame(['first', 'second', 'destination'], MiddlewareOrder::$log);
    }
}

// DTOs for Serializer tests

#[ResponseDto(case: 'snake_case')]
readonly class SnakeCaseDto
{
    public function __construct(
        public string $firstName,
        public string $emailAddress,
    ) {}
}

#[ResponseDto(case: 'snake_case')]
readonly class HiddenFieldDto
{
    public function __construct(
        public string $name,
        #[Hide]
        public string $password,
    ) {}
}

#[ResponseDto(case: 'snake_case')]
readonly class SerializeAsDto
{
    public function __construct(
        #[SerializeAs('full_name')]
        public string $name,
    ) {}
}

#[ResponseDto(case: 'snake_case')]
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