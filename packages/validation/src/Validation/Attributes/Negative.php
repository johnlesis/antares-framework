<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Negative implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_numeric($value) && $value >= 0) {
            return "The value must be a negative number.";
        }

        return null;
    }
}