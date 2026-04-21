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

        foreach ($value as $index => $item) {
            if (!($item instanceof $this->type)) {
                return "Item at index {$index} must be an instance of {$this->type}.";
            }
        }

        return null;
    }
}