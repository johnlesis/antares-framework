<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Json implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return "The value must be a valid JSON string.";
            }
        }

        return null;
    }
}