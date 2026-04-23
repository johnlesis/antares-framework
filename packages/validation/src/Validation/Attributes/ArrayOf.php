<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ArrayOf implements ValidationAttribute
{
    public function __construct(
        public readonly string $type,
    ) {}

    public function validate(mixed $value): ?string
    {
        if (!is_array($value)) {
            return "The value must be an array.";
        }

        $primitives = ['string', 'int', 'float', 'bool', 'array'];

        foreach ($value as $index => $item) {
            if (in_array($this->type, $primitives)) {
                $passes = match($this->type) {
                    'string' => is_string($item),
                    'int'    => is_int($item),
                    'float'  => is_float($item),
                    'bool'   => is_bool($item),
                    'array'  => is_array($item),
                };

                if (!$passes) {
                    return "Item at index {$index} must be of type {$this->type}.";
                }
            } else {
                if (!($item instanceof $this->type)) {
                    return "Item at index {$index} must be an instance of {$this->type}.";
                }
            }
        }

        return null;
    }
}