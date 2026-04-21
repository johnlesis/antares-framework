<?php declare (strict_types=1);

namespace Antares\Validation\Attributes;

interface ValidationAttribute
{
    public function validate(mixed $value): ?string;
}