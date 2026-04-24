<?php declare(strict_types=1);

namespace Antares\Validation;

use Antares\Validation\Attributes\ValidationAttribute;
use Antares\Validation\Exceptions\ValidationException;

final class Validator
{
    /**
     * @throws ValidationException
     */
    public function validate(object $dto): void
    {   

        $reflectedDTO = new \ReflectionClass($dto);

        $errors = [];
        foreach ($reflectedDTO->getConstructor()->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(ValidationAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attributes as $attribute) {
                $name = $parameter->getName();    
                $value = $dto->$name;            
                $attributeInstance = $attribute->newInstance();
                $error = $attributeInstance->validate($value);
                if ($error !== null) {
                    $errors[$name][] = $error;
                }
            }
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}