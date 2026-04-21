<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class In implements ValidationAttribute
{
    public function __construct(
        public readonly array $values,
    ) {}

    public function validate(mixed $value): ?string
    {
        if (!in_array($value, $this->values, strict: true)) {
            $allowed = implode(', ', $this->values);
            return "The value must be one of: {$allowed}.";
        }

        return null;
    }
}