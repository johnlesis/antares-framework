<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Max implements ValidationAttribute
{

    public function __construct(
        public readonly int|float $max
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_numeric($value) && $value > $this->max) {
            return "The value must be at most {$this->max}.";
        }

        return null;
    }
}
