<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Alpha implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !ctype_alpha($value)) {
            return "The value must contain only letters.";
        }

        return null;
    }
}