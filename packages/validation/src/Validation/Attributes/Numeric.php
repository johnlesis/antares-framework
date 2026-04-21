<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Numeric implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !ctype_digit($value)) {
            return "The value must contain only digits.";
        }

        return null;
    }
}