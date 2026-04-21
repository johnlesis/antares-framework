<?php

declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class MinLength implements ValidationAttribute
{

    public function __construct(
        public readonly int $minLength
    ) {}

    public function validate(mixed $value): ?string
    {
        if (is_string($value) && mb_strlen($value) < $this->minLength) {
            return "The value must be at least {$this->minLength} characters long.";
        }

        return null;
    }
}
