<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class HexColor implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return "The value must be a valid hex color.";
        }

        return null;
    }
}