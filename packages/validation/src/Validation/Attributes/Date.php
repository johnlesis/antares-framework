<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Date implements ValidationAttribute
{
    public function __construct(
        public readonly string $format = 'Y-m-d',
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_string($value)) {
            $date = \DateTimeImmutable::createFromFormat($this->format, $value);
            if (!$date || $date->format($this->format) !== $value) {
                return "The value must be a valid date in format {$this->format}.";
            }
        }

        return null;
    }
}