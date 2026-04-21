<?php declare(strict_types=1);

namespace Antares\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class InEnum implements ValidationAttribute
{
    public function __construct(
        public readonly string $enumClass
    ) {}

    public function validate(mixed $value): ?string
    {
        if (!enum_exists($this->enumClass)) {
            throw new \InvalidArgumentException("The provided class '{$this->enumClass}' is not a valid enum.");
        }
        

        $reflectionEnum = new \ReflectionEnum($this->enumClass);

        if (!$reflectionEnum->isBacked()) {
            throw new \InvalidArgumentException("The provided enum class '{$this->enumClass}' must be a backed enum.");
        }

        if ($this->enumClass::tryFrom($value) === null) {
            $validValues = array_map(fn($case) => $case->value, $this->enumClass::cases());
            $valuesString = implode(', ', $validValues);
            return "The value must be one of the following: " . $valuesString . ".";
        }

        return null;
    }
       
}