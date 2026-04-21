<?php declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class NotBlank implements ValidationAttribute
{
    public function validate(mixed $value): ?string
    {
        if (is_string($value) && trim($value) === '') {
            return "The value must not be blank.";
        }

        return null;
    }
}