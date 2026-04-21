<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class NotNull implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if ($value === null) {
            return "The value must not be null.";
        }

        return null;
    }
}