<?php

declare(strict_types=1);

namespace Antares\Hydration;

use Antares\Hydration\Exceptions\HydrationException;
use Antares\Validation\Attributes\Dto;
use Antares\Validation\Attributes\Strict;
use Antares\Validation\Validator;

final class Hydrator
{

    public function __construct(
        private readonly Validator $validator
    ) {}

    /**
     * @throws HydrationException
     */
    public function hydrate(string $className, array $data): object
    {
        $reflectionClass = new \ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        // A mistake in the DTO class, not in the request: hide it from the client
        if ($constructor === null) {
            throw new \LogicException("Cannot hydrate {$className}: the class must have a constructor.");
        }

        $parameters = $constructor->getParameters();

        // If strict mode is enabled we do not let extra request fields pass
        $isStrict = !empty($reflectionClass->getAttributes(Strict::class));
        if ($isStrict) {
            $validKeys = array_map(fn($parameter) => $parameter->getName(), $parameters);
            $extraKeys = array_diff(array_keys($data), $validKeys);
            if (!empty($extraKeys)) {
                throw new HydrationException(
                    "Unknown fields: " . implode(', ', $extraKeys)
                );
            }
        }

        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            if (!array_key_exists($name, $data)) {
                if ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                    continue;
                }
                if ($parameter->allowsNull()) {
                    $args[] = null;
                    continue;
                }
                throw new HydrationException("Missing required parameter: $name");
            }

            if (!$type instanceof \ReflectionNamedType) {
                $args[] = $data[$name];
                continue;
            }

            $value = $data[$name];

            if ($value === null) {
                if ($type->allowsNull()) {
                    $args[] = null;
                    continue;
                }
                throw new HydrationException("Parameter {$name} must not be null.");
            }

            if (!$type->isBuiltin()) {
                $ref = new \ReflectionClass($type->getName());
                if (empty($ref->getAttributes(Dto::class))) {
                    // A mistake in the DTO class, not in the request: hide it from the client
                    throw new \LogicException(
                        "Cannot hydrate {$className}::\${$name}: {$type->getName()} is not marked with #[Dto]."
                    );
                }
                if (!is_array($value)) {
                    throw new HydrationException("Parameter {$name} must be an object.");
                }
                $args[] = $this->hydrate($type->getName(), $value);
                continue;
            }

            if ($type->getName() === 'int') {
                if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new HydrationException("Parameter {$name} must be a valid integer.");
                }
                $args[] = (int) $value;
                continue;
            }
            if ($type->getName() === 'float') {
                if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                    throw new HydrationException("Parameter {$name} must be a valid float.");
                }
                $args[] = (float) $value;
                continue;
            }
            if ($type->getName() === 'bool') {
                $bool = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
                if ($bool === null) {
                    throw new HydrationException("Parameter {$name} must be a valid boolean.");
                }
                $args[] = $bool;
                continue;
            }
            if ($type->getName() === 'string') {
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    throw new HydrationException("Parameter {$name} must be a string.");
                }
                $args[] = (string) $value;
                continue;
            }
            if ($type->getName() === 'array' && !is_array($value)) {
                throw new HydrationException("Parameter {$name} must be an array.");
            }
            $args[] = $value;
        }
        
        $instance =  $reflectionClass->newInstanceArgs($args);
        $this->validator->validate($instance);
        return $instance;
    }
}
