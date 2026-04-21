<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class AlphaNumeric implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !ctype_alnum($value)) {
            return "The value must contain only letters and numbers.";
        }

        return null;
    }
}