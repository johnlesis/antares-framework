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

        if ($constructor === null) {
            throw new HydrationException("The class must have a constructor.");
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
            
            if (!$type->isBuiltin()) {
                $ref = new \ReflectionClass($type->getName());
                if (!empty($ref->getAttributes(Dto::class))) {
                    $args[] = $this->hydrate($type->getName(), $data[$name]);
                    continue;
                }
                throw new HydrationException("Cannot hydrate parameter {$name}: class is not marked with #[Dto].");
            }

            if ($type->getName() === 'int') {
                if (filter_var($data[$name], FILTER_VALIDATE_INT) === false) {
                    throw new HydrationException("Parameter {$name} must be a valid integer.");
                }
                $args[] = (int) $data[$name];
                continue;
            }
            if ($type->getName() === 'float') {
                if (filter_var($data[$name], FILTER_VALIDATE_FLOAT) === false) {
                    throw new HydrationException("Parameter {$name} must be a valid float.");
                }
                $args[] = (float)$data[$name];
                continue;
            }
            if ($type->getName() === 'bool') {
                if (filter_var($data[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                    throw new HydrationException("Parameter {$name} must be a valid boolean.");
                }
                $args[] = (bool)$data[$name];
                continue;
            }
            if ($type->getName() === 'string') {
                $args[] = (string)$data[$name];
                continue;
            }
            $args[] = $data[$name];
        }
        
        $instance =  $reflectionClass->newInstanceArgs($args);
        $this->validator->validate($instance);
        return $instance;
    }
}
