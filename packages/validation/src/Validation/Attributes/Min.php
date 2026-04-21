<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Min implements ValidationAttribute
{

    public function __construct(
        public readonly int|float $min
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_numeric($value) && $value < $this->min) {
            return "The value must be at least {$this->min}.";
        }

        return null;
    }
}
