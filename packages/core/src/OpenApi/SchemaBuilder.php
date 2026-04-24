<?php

declare(strict_types=1);

namespace Antares\OpenApi;

use Antares\Validation\Attributes\Email;
use Antares\Validation\Attributes\Min;
use Antares\Validation\Attributes\Max;
use Antares\Validation\Attributes\MinLength;
use Antares\Validation\Attributes\MaxLength;
use Antares\Validation\Attributes\Pattern;
use Antares\Validation\Attributes\ValidationAttribute;
use Antares\Serialization\Attributes\Hide;
use Antares\Serialization\Attributes\SerializeAs;
use Antares\Serialization\Attributes\ResponseDto;
use Antares\Support\CaseConverter;
use Antares\Validation\Attributes\ArrayOf;
use Antares\Validation\Attributes\Between;
use Antares\Validation\Attributes\Date;
use Antares\Validation\Attributes\DateTime;
use Antares\Validation\Attributes\HexColor;
use Antares\Validation\Attributes\In;
use Antares\Validation\Attributes\Ip;
use Antares\Validation\Attributes\Json;
use Antares\Validation\Attributes\Negative;
use Antares\Validation\Attributes\Phone;
use Antares\Validation\Attributes\Positive;
use Antares\Validation\Attributes\Size;
use Antares\Validation\Attributes\Uuid;
use Antares\Validation\Attributes\Url;

final class SchemaBuilder
{
    public function build(string $dtoClass): array
    {
        $reflection = new \ReflectionClass($dtoClass);
        $parameters = $reflection->getConstructor()->getParameters();

        $responseDtoAttr = $reflection->getAttributes(ResponseDto::class);
        $case = !empty($responseDtoAttr) ? $responseDtoAttr[0]->newInstance()->case : null;

        $properties = [];
        $required   = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            if (!empty($parameter->getAttributes(Hide::class))) {
                continue;
            }

            $serializeAs = $parameter->getAttributes(SerializeAs::class);
            $name = !empty($serializeAs)
                ? $serializeAs[0]->newInstance()->name
                : ($case ? CaseConverter::convert($name, $case) : $parameter->getName());
            
            if ($type === null) {
                $properties[$name] = ['type' => 'string'];
                continue;
            }

            $property = ['type' => $type instanceof \ReflectionNamedType ? $this->mapType($type) : 'string'];

            if ($type->allowsNull()) {
                $property['nullable'] = true;
            }

            if (!$parameter->isDefaultValueAvailable() && !$type->allowsNull()) {
                $required[] = $name;
            }

            $attributes = $parameter->getAttributes(
                ValidationAttribute::class,
                \ReflectionAttribute::IS_INSTANCEOF
            );

            $property = $this->mapAttributtes($property, $attributes);
            
            $properties[$name] = $property;
        }

        return [
            'type'       => 'object',
            'required'   => $required,
            'properties' => $properties,
        ];
    }

    private function mapType(\ReflectionNamedType $type): string
    {
        return match ($type->getName()) {
            'int'    => 'integer',
            'float'  => 'number',
            'bool'   => 'boolean',
            'array'  => 'array',
            default  => 'string',
        };
    }

    private function mapAttributtes(array $property, array $attributes): array
    {
        foreach ($attributes as $attribute) {
            $instance   = $attribute->newInstance();
            $constraint = match(true) {
                $instance instanceof MinLength    => ['minLength' => $instance->minLength],
                $instance instanceof MaxLength    => ['maxLength' => $instance->maxLength],
                $instance instanceof Min          => ['minimum'   => $instance->min],
                $instance instanceof Max          => ['maximum'   => $instance->max],
                $instance instanceof Email        => ['format'    => 'email'],
                $instance instanceof Pattern      => ['pattern'   => $instance->regex],
                $instance instanceof Uuid         => ['format'    => 'uuid'],
                $instance instanceof Url          => ['format'    => 'uri'],
                $instance instanceof Date         => ['format'    => 'date'],
                $instance instanceof DateTime     => ['format'    => 'date-time'],
                $instance instanceof Ip           => ['format'    => 'ipv4'],
                $instance instanceof HexColor     => ['pattern'   => '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$'],
                $instance instanceof Json         => ['type'      => 'string', 'format' => 'json'],
                $instance instanceof Between      => ['minimum'   => $instance->min, 'maximum' => $instance->max],
                $instance instanceof Size         => ['minLength' => $instance->min, 'maxLength' => $instance->max],
                $instance instanceof Positive     => ['minimum'   => 1],
                $instance instanceof Negative     => ['maximum'   => -1],
                $instance instanceof In           => ['enum'      => $instance->values],
                $instance instanceof Phone        => ['pattern'   => '^\+?[0-9\s\-\(\)]{7,20}$'],
                $instance instanceof ArrayOf      => ['type'      => 'array', 'items' => ['$ref' => '#/components/schemas/' . (new \ReflectionClass($instance->type))->getShortName()]],
                default                           => [],
            };
            $property = array_merge($property, $constraint);
        }
        return $property;
    }
}
