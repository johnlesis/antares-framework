<?php declare(strict_types=1);

namespace Antares\Serialization;

use Antares\Serialization\Attributes\Computed;
use Antares\Serialization\Attributes\Hide;
use Antares\Serialization\Attributes\ResponseDto;
use Antares\Serialization\Attributes\SerializeAs;
use Antares\Support\CaseConverter;
use ReflectionClass;

final class Serializer
{
    public function serialize(object $dto): array
    {
        $reflection = new ReflectionClass($dto);
        $attributes = $reflection->getAttributes(ResponseDto::class);
        $case = !empty($attributes) ? $attributes[0]->newInstance()->case : 'camel_case';

        $result = [];
        foreach ($reflection->getProperties() as $property) {
            if (!empty($property->getAttributes(Hide::class))) {
                continue;
            }

            $serializeAs = $property->getAttributes(SerializeAs::class);
            $key = !empty($serializeAs)
                ? $serializeAs[0]->newInstance()->name
                : CaseConverter::convert($property->getName(), $case);

            $result[$key] = $this->serializeValue($property->getValue($dto));
        }

        foreach ($reflection->getMethods() as $method) {
            if (!empty($method->getAttributes(Computed::class))) {
                $name = $this->stripGet($method->getName());
                $key  = CaseConverter::convert($name, $case);
                $result[$key] = $this->serializeValue($method->invoke($dto));
            }
        }

        return $result;
    }

    private function serializeValue(mixed $value): mixed
    {
        if (is_object($value) && $this->isResponseDto($value)) {
            return $this->serialize($value);
        }

        if (is_array($value)) {
            return array_map(fn($item) => $this->serializeValue($item), $value);
        }

        return $value;
    }

    private function isResponseDto(object $value): bool
    {
        return !empty((new ReflectionClass($value))->getAttributes(ResponseDto::class));
    }

    private function stripGet(string $name): string
    {
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            return lcfirst(substr($name, 3));
        }

        return $name;
    }
}