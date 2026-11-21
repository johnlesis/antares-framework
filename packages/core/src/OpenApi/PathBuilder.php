<?php declare(strict_types=1);

namespace Antares\OpenApi;

use Antares\Http\ResolverInterfaces\Resolvable;
use Antares\Validation\Attributes\Dto;

final class PathBuilder
{
    public function build(array $route): array
    {
        $httpVerb   = strtolower($route[0]);
        $path       = $route[1];
        $controller = $route[2];
        $method     = $route[3];
        $statusCode = $route[4];

        preg_match_all('/\{(\w+)\}/', $path, $matches);
        $pathParams = $matches[1];

        $reflection = new \ReflectionMethod($controller, $method);
        $parameters = $reflection->getParameters();

        $pathParameters  = [];
        $queryParameters = [];
        $requestBody    = null;
        $responseRef    = null;

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            // Resolved by an attribute (#[Inject], #[Guard], ...), not from the request
            if (!empty($parameter->getAttributes(Resolvable::class, \ReflectionAttribute::IS_INSTANCEOF))) {
                continue;
            }

            $typeName = $type->getName();

            if (in_array($parameter->getName(), $pathParams)) {
                $pathParameters[] = [
                    'name'     => $parameter->getName(),
                    'in'       => 'path',
                    'required' => true,
                    'schema'   => ['type' => $this->mapType($type)],
                ];
                continue;
            }

            if (!$type->isBuiltin() && class_exists($typeName)) {
                $ref = new \ReflectionClass($typeName);
                if (!empty($ref->getAttributes(Dto::class))) {
                    $requestBody = [
                        'required' => true,
                        'content'  => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$typeName}"],
                            ],
                        ],
                    ];
                }
                continue;
            }

            // Scalars not in the path are read from the query string by the Dispatcher
            if (in_array($typeName, ['int', 'float', 'bool', 'string'], true)) {
                $schema = ['type' => $this->mapType($type)];
                if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() !== null) {
                    $schema['default'] = $parameter->getDefaultValue();
                }
                $queryParameters[] = [
                    'name'     => $parameter->getName(),
                    'in'       => 'query',
                    'required' => !$parameter->isDefaultValueAvailable(),
                    'schema'   => $schema,
                ];
            }
        }
        //response
        $returnType = $reflection->getReturnType();
        if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin()) {
            $responseRef = ['$ref' => "#/components/schemas/{$returnType->getName()}"];
        }

        $operation = [
            'summary'   => ucfirst($method),
            'responses' => [
                (string) $statusCode => [
                    'description' => 'Success',
                    'content'     => [
                        'application/json' => [
                            'schema' => $responseRef ?? ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];

        $operationParameters = array_merge($pathParameters, $queryParameters);
        if (!empty($operationParameters)) {
            $operation['parameters'] = $operationParameters;
        }

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        return [
            $path => [
                $httpVerb => $operation,
            ],
        ];
    }

    private function mapType(\ReflectionNamedType $type): string
    {
        return match($type->getName()) {
            'int'   => 'integer',
            'float' => 'number',
            'bool'  => 'boolean',
            default => 'string',
        };
    }
}