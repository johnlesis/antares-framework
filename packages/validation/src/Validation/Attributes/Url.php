<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Url implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            return "The value must be a valid URL.";
        }

        return null;
    }
}