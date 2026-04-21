<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class MaxLength implements ValidationAttribute
{

    public function __construct(
        public readonly int $maxLength
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_string($value) && mb_strlen($value) > $this->maxLength) {
            return "The value must be at most {$this->maxLength} characters long.";
        }

        return null;
    }
}
