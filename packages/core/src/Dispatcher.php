<?php
declare(strict_types=1);
namespace Antares;
use Antares\Container\Container;
use Antares\Exceptions\HttpException;
use Antares\Http\Attributes\Guards;
use Antares\Http\ResponseBag;
use Antares\Hydration\Hydrator;
use Antares\Router\Router;
use Antares\Serialization\Serializer;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\File;
use Antares\Validation\Exceptions\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class Dispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly Hydrator $hydrator,
        private readonly Serializer $serializer
    ) {}

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $httpMethod = $request->getMethod();
        $uri = $request->getUri()->getPath();
        [$controllerClass, $methodName, $routeParams, $statusCode] = $this->router->match($httpMethod, $uri);
        $controller = $this->container->make($controllerClass);
        $reflection = new \ReflectionMethod($controller, $methodName);
        $parameters = $reflection->getParameters();
        $args = [];
        foreach ($parameters as $parameter) {
            $args[] = $this->resolveParameter($parameter, $routeParams, $request);
        }
        $result = $reflection->invokeArgs($controller, $args);
        return $this->buildResponse($result, $statusCode);
    }

    private function resolveParameter(
        \ReflectionParameter $parameter,
        array $routeParams,
        ServerRequestInterface $request,
    ): mixed {
        $type = $parameter->getType();

        $guardsAttr = $parameter->getAttributes(Guards::class);
        if (!empty($guardsAttr)) {
            $guardClass = $guardsAttr[0]->newInstance()->guardClass;
            $guard = $this->container->make($guardClass);
            return $guard->resolve($request);
        }

        if (!$type instanceof \ReflectionNamedType) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new HttpException(500, "Cannot resolve parameter \${$parameter->getName()}: no type hint and no default value.");
        }

        $typeName = $type->getName();

        if ($typeName === ServerRequestInterface::class) {
            return $request;
        }

        if ($typeName === UploadedFileInterface::class) {
            $files = $request->getUploadedFiles();
            $file = $files[$parameter->getName()] ?? null;

            if ($file === null) {
                throw new HttpException(400, "Missing file: {$parameter->getName()}");
            }

            $fileAttrs = $parameter->getAttributes(File::class);
            if (!empty($fileAttrs)) {
                $error = $fileAttrs[0]->newInstance()->validate($file);
                if ($error !== null) {
                    throw new ValidationException([$parameter->getName() => [$error]]);
                }
            }

            return $file;
        }

        if (isset($routeParams[$parameter->getName()])) {
            return $this->castRouteParam($routeParams[$parameter->getName()], $type);
        }

        if (!$type->isBuiltin()) {
            $ref = new \ReflectionClass($typeName);
            if (!empty($ref->getAttributes(Dto::class))) {
                $rawBody = (string) $request->getBody();
                $contentType = $request->getHeaderLine('Content-Type');

                if (str_contains($contentType, 'application/x-www-form-urlencoded') || str_contains($contentType, 'multipart/form-data')) {
                    $parsed = $request->getParsedBody();
                    if (!is_array($parsed)) {
                        throw new HttpException(400, "Invalid request body.");
                    }
                    $data = $parsed;
                } else {
                    if (!empty($rawBody) && json_decode($rawBody) === null) {
                        throw new HttpException(400, "Invalid JSON body.");
                    }
                    $data = json_decode($rawBody, true) ?? [];
                }
                return $this->hydrator->hydrate($typeName, $data);
            }
            return $this->container->make($typeName);
        }

        $queryParams = $request->getQueryParams();
        if (isset($queryParams[$parameter->getName()])) {
            return $this->castRouteParam($queryParams[$parameter->getName()], $type);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new HttpException(500, "Cannot resolve parameter \${$parameter->getName()} in controller.");
    }

    private function castRouteParam(string $value, ?\ReflectionNamedType $type): mixed
    {
        return match($type?->getName()) {
            'int'   => filter_var($value, FILTER_VALIDATE_INT) !== false
                            ? (int) $value
                            : throw new HttpException(400, "Route parameter must be a valid integer, got '{$value}'"),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) !== false
                            ? (float) $value
                            : throw new HttpException(400, "Route parameter must be a valid float, got '{$value}'"),
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null
                            ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                            : throw new HttpException(400, "Route parameter must be a valid boolean, got '{$value}'"),
            default => $value,
        };
    }

    private function buildResponse(mixed $result, int $statusCode): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $this->applyResponseBag($result);
        }

        if ($result === null) {
            return $this->applyResponseBag(new Response(status: $statusCode));
        }

        $body = match(true) {
            is_array($result)  => $this->encodeJson($result),
           is_object($result) => $this->isResponseDto($result)
            ? $this->encodeJson($this->serializer->serialize($result))
            : throw new HttpException(500, "Controller returned an object without #[ResponseDto]. Add the attribute or return an array."),
            default            => $this->encodeJson($result),
        };

        return $this->applyResponseBag(new Response(
            status: $statusCode,
            headers: ['Content-Type' => 'application/json'],
            body: $body,
        ));
    }

    private function encodeJson(mixed $data): string
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            throw new HttpException(500, "Failed to serialize response body.");
        }
        return $encoded;
    }

    private function applyResponseBag(ResponseInterface $response): ResponseInterface
    {
        foreach (ResponseBag::getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        ResponseBag::clear();

        return $response;
    }

    private function isResponseDto(object $result): bool
    {
        $reflection = new \ReflectionClass($result);
        return !empty($reflection->getAttributes(\Antares\Serialization\Attributes\ResponseDto::class));
    }
}