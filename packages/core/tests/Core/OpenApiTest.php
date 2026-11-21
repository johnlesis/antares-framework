<?php

declare(strict_types=1);

namespace Antares\Tests\Core\OpenApi;

use Antares\Application;
use Antares\Http\Attributes\Inject;
use Antares\OpenApi\Generator;
use Antares\Serialization\Attributes\Computed;
use Antares\Serialization\CaseStyle;
use Nyholm\Psr7\ServerRequest;
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Router;
use Antares\Serialization\Attributes\ResponseDto;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\Email;
use PHPUnit\Framework\TestCase;

class OpenApiTest extends TestCase
{
    private function spec(): array
    {
        $router = new Router();
        $router->register(DocsController::class);
        return (new Generator($router))->generate();
    }

    public function test_dto_request_body_is_detected_without_readonly(): void
    {
        $operation = $this->spec()['paths']['/users']['post'];
        $this->assertSame(
            ['$ref' => '#/components/schemas/' . CreateUserRequest::class],
            $operation['requestBody']['content']['application/json']['schema'],
        );
    }

    public function test_readonly_non_dto_parameter_is_not_a_request_body(): void
    {
        $spec = $this->spec();
        $this->assertArrayNotHasKey('requestBody', $spec['paths']['/status']['get']);
        $this->assertArrayNotHasKey(SomeReadonlyService::class, $spec['components']['schemas']);
    }

    public function test_dto_without_constructor_does_not_crash(): void
    {
        $this->assertSame(
            ['type' => 'object', 'required' => [], 'properties' => []],
            $this->spec()['components']['schemas'][EmptyRequest::class],
        );
    }

    public function test_request_schema_includes_validation_constraints(): void
    {
        $schema = $this->spec()['components']['schemas'][CreateUserRequest::class];
        $this->assertSame(['email'], $schema['required']);
        $this->assertSame(['type' => 'string', 'format' => 'email'], $schema['properties']['email']);
    }

    // Query parameters

    public function test_scalar_parameters_outside_the_path_are_query_parameters(): void
    {
        $params = $this->spec()['paths']['/users/{id}/posts']['get']['parameters'];

        $this->assertSame([
            ['name' => 'id',     'in' => 'path',  'required' => true,  'schema' => ['type' => 'integer']],
            ['name' => 'search', 'in' => 'query', 'required' => true,  'schema' => ['type' => 'string']],
            ['name' => 'page',   'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 1]],
            ['name' => 'draft',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean', 'default' => false]],
        ], $params);
    }

    public function test_injected_and_request_parameters_are_not_query_parameters(): void
    {
        $operation = $this->spec()['paths']['/me']['get'];
        $this->assertArrayNotHasKey('parameters', $operation);
    }

    // Computed fields

    public function test_computed_fields_are_in_the_response_schema(): void
    {
        $schema = $this->spec()['components']['schemas'][ProfileResponse::class];

        $this->assertSame(['type' => 'string', 'readOnly' => true], $schema['properties']['full_name']);
        $this->assertSame(['type' => 'string', 'readOnly' => true, 'nullable' => true], $schema['properties']['nickname']);
        $this->assertContains('full_name', $schema['required']);
        $this->assertNotContains('nickname', $schema['required']);
    }

    // APP_DOCS / APP_ENV

    /** @return array<string, array{array<string, string>, bool}> */
    public static function docsSwitchCases(): array
    {
        return [
            'local, no flag'           => [['APP_ENV' => 'local'], true],
            'production, no flag'      => [['APP_ENV' => 'production'], false],
            'production, APP_DOCS on'  => [['APP_ENV' => 'production', 'APP_DOCS' => 'true'], true],
            'local, APP_DOCS off'      => [['APP_ENV' => 'local', 'APP_DOCS' => 'false'], false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('docsSwitchCases')]
    public function test_docs_endpoints_follow_app_docs_and_app_env(array $env, bool $expectDocs): void
    {
        $basePath = sys_get_temp_dir() . '/antares-docs-test-' . bin2hex(random_bytes(4));
        mkdir($basePath);
        $backup = $_ENV;

        try {
            unset($_ENV['APP_ENV'], $_ENV['APP_DOCS']);
            $_ENV = array_merge($_ENV, $env);

            $app = Application::create($basePath);
            $app->boot();

            $status = $app->handle(new ServerRequest('GET', '/openapi.json'))->getStatusCode();
            $expectDocs ? $this->assertSame(200, $status) : $this->assertNotSame(200, $status);
        } finally {
            $_ENV = $backup;
            @unlink($basePath . '/storage/cache/routes.php');
            @unlink($basePath . '/storage/cache/fingerprint');
            @rmdir($basePath . '/storage/cache');
            @rmdir($basePath . '/storage');
            @rmdir($basePath);
        }
    }
}

#[Dto]
final class CreateUserRequest
{
    public function __construct(
        #[Email]
        public string $email,
    ) {}
}

#[Dto]
final class EmptyRequest {}

#[ResponseDto(case: CaseStyle::SnakeCase)]
final readonly class ProfileResponse
{
    public function __construct(public string $firstName, public string $lastName) {}

    #[Computed]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    #[Computed]
    public function nickname(): ?string
    {
        return null;
    }
}

final class CurrentUserResolver
{
    public function resolve(): int
    {
        return 1;
    }
}

#[ResponseDto]
final readonly class UserResponse
{
    public function __construct(public int $id) {}
}

final readonly class SomeReadonlyService {}

final class DocsController
{
    #[Post('/users', 201)]
    public function store(CreateUserRequest $request): UserResponse
    {
        return new UserResponse(1);
    }

    #[Get('/status')]
    public function status(SomeReadonlyService $service): array
    {
        return [];
    }

    #[Get('/users/{id}/posts')]
    public function posts(int $id, string $search, int $page = 1, bool $draft = false): array
    {
        return [];
    }

    #[Get('/me')]
    public function me(#[Inject(CurrentUserResolver::class)] int $userId, \Psr\Http\Message\ServerRequestInterface $request): ProfileResponse
    {
        return new ProfileResponse('John', 'Doe');
    }

    #[Post('/ping')]
    public function ping(EmptyRequest $request): array
    {
        return [];
    }
}
