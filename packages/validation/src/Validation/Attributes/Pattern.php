<?php declare (strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Pattern implements ValidationAttribute
{
    public function __construct(
        public readonly string $regex
    ) {}

    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $result = preg_match($this->regex, $value);

        if ($result === false) {
            throw new \RuntimeException("An error occurred while evaluating the regex pattern.");
        }

        if ($result === 0) {
            return "does not match pattern";
        }

        return null;
    }
}