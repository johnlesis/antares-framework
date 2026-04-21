<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Size implements ValidationAttribute
{
    public function __construct(
        public readonly int $min,
        public readonly int $max,
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_string($value)) {
            $length = mb_strlen($value);
            if ($length < $this->min || $length > $this->max) {
                return "The value must be between {$this->min} and {$this->max} characters.";
            }
        }

        return null;
    }
}