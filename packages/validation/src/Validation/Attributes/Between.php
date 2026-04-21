<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Between implements ValidationAttribute
{
    public function __construct(
        public readonly int|float $min,
        public readonly int|float $max,
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_numeric($value) && ($value < $this->min || $value > $this->max)) {
            return "The value must be between {$this->min} and {$this->max}.";
        }

        return null;
    }
}