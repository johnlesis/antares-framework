<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Phone implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $value)) {
            return "The value must be a valid phone number.";
        }

        return null;
    }
}