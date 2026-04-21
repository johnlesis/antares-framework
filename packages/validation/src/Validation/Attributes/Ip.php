<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Ip implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
            return "The value must be a valid IP address.";
        }

        return null;
    }
}