<?php

declare(strict_types=1);

namespace Antares\Tests\Core\Input;

use Antares\Application;
use Antares\Container\Container;
use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Router;
use Antares\ServiceProvider;
use Antares\Validation\Attributes\Dto;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Malformed requests must get a 4xx with a clear message, never a 500.
 * Mistakes in the app's own classes must get a hidden 500.
 */
class RequestInputTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        InputLoggerProvider::$logger = new InputLogger();

        $backup = $_ENV;
        try {
            $_ENV = array_merge($_ENV, ['APP_DOCS' => 'false', 'APP_DEBUG' => 'false']);
            $this->app = Application::create(sys_get_temp_dir())
                ->providers([InputLoggerProvider::class])
                ->routeProviders([InputRoutes::class]);
            $this->app->boot();
        } finally {
            $_ENV = $backup;
        }
    }

    private function json(string $path, string $body): ResponseInterface
    {
        return $this->app->handle(new ServerRequest('POST', $path, ['Content-Type' => 'application/json'], $body));
    }

    private function title(ResponseInterface $response): string
    {
        return json_decode((string) $response->getBody(), true)['title'];
    }

    /** @return array<string, array{string, string}> */
    public static function badJsonBodies(): array
    {
        return [
            'invalid JSON'           => ['{"name": ', 'Invalid JSON body.'],
            'a JSON string'          => ['"hi"', 'Request body must be a JSON object.'],
            'a JSON number'          => ['123', 'Request body must be a JSON object.'],
            'JSON null'              => ['null', 'Request body must be a JSON object.'],
            'nested dto as a string' => ['{"name": "a", "address": "Main St"}', 'Parameter address must be an object.'],
            'array for a string'     => ['{"name": ["a", "b"]}', 'Parameter name must be a string.'],
        ];
    }

    #[DataProvider('badJsonBodies')]
    public function test_bad_json_bodies_get_a_400(string $body, string $title): void
    {
        $response = $this->json('/signup', $body);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame($title, $this->title($response));
    }

    public function test_empty_body_counts_as_an_empty_object(): void
    {
        $response = $this->json('/signup', '');
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Missing required parameter: name', $this->title($response));
    }

    public function test_query_parameter_given_as_an_array_gets_a_400(): void
    {
        $request = (new ServerRequest('GET', '/list'))->withQueryParams(['page' => ['1']]);
        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Query parameter page must be a single value.', $this->title($response));
    }

    public function test_invalid_query_value_names_the_parameter(): void
    {
        $request = (new ServerRequest('GET', '/list'))->withQueryParams(['page' => 'abc']);
        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame("Query parameter page must be a valid integer, got 'abc'.", $this->title($response));
    }

    public function test_invalid_route_value_names_the_parameter(): void
    {
        $response = $this->app->handle(new ServerRequest('GET', '/items/abc'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame("Route parameter id must be a valid integer, got 'abc'.", $this->title($response));
    }

    public function test_several_files_under_one_name_get_a_400(): void
    {
        $file = fn () => new UploadedFile(Stream::create('x'), 1, UPLOAD_ERR_OK, 'a.png', 'image/png');
        $request = (new ServerRequest('POST', '/avatar'))->withUploadedFiles(['avatar' => [$file(), $file()]]);
        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Expected a single file for avatar.', $this->title($response));
    }

    public function test_mistake_in_a_dto_class_is_a_hidden_500(): void
    {
        $response = $this->json('/broken-dto', '{"thing": {"x": "y"}}');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $this->title($response));
        $this->assertStringNotContainsString('#[Dto]', (string) $response->getBody());
        $this->assertCount(1, InputLoggerProvider::$logger->records);
    }

    public function test_client_errors_are_not_logged(): void
    {
        $this->json('/signup', '"hi"');
        $this->assertSame([], InputLoggerProvider::$logger->records);
    }
}

#[Dto]
final class AddressInput
{
    public function __construct(public string $street) {}
}

#[Dto]
final class SignupInput
{
    public function __construct(public string $name, public ?AddressInput $address = null) {}
}

final class NotADtoInput
{
    public function __construct(public string $x) {}
}

#[Dto]
final class BrokenInput
{
    public function __construct(public NotADtoInput $thing) {}
}

final class InputController
{
    #[Post('/signup', 201)]
    public function signup(SignupInput $input): array
    {
        return ['name' => $input->name];
    }

    #[Get('/list')]
    public function list(int $page = 1): array
    {
        return ['page' => $page];
    }

    #[Get('/items/{id}')]
    public function show(int $id): array
    {
        return ['id' => $id];
    }

    #[Post('/avatar', 201)]
    public function avatar(UploadedFileInterface $avatar): array
    {
        return [];
    }

    #[Post('/broken-dto')]
    public function broken(BrokenInput $input): array
    {
        return [];
    }
}

final class InputRoutes implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->make(Router::class)->register(InputController::class);
    }
}

final class InputLogger extends AbstractLogger
{
    /** @var list<array{string, string}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message];
    }
}

final class InputLoggerProvider implements ServiceProvider
{
    public static ?InputLogger $logger = null;

    public function register(Container $container): void
    {
        $container->singleton(LoggerInterface::class, fn () => self::$logger);
    }
}
