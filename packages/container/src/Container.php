<?php declare(strict_types=1);

namespace Antares\Container;

use ReflectionClass;

final class Container
{   
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    private array $building = [];
    private array $scopedBindings = [];
    private array $scopedInstances = [];

    public function scoped(string $className, callable $callable): void
    {
        $this->scopedBindings[$className] = $callable;
    }

    public function clearScoped(): void
    {
        $this->scopedInstances = [];
    }

    public function bind(string $interface, string $className): void
    {
        $this->bindings[$interface] = $className;
    }

    public function singleton(string $className, callable $callable): void
    {
        $this->singletons[$className] = $callable;
    }

    public function make(string $className)
    {
        if (isset($this->instances[$className])) {
            return $this->instances[$className];
        }

        if (isset($this->scopedBindings[$className])) {
            if (!isset($this->scopedInstances[$className])) {
                $this->scopedInstances[$className] = ($this->scopedBindings[$className])($this);
            }
            return $this->scopedInstances[$className];
        }

        if (isset($this->singletons[$className])) {
            $this->instances[$className] = ($this->singletons[$className])($this);
            return $this->instances[$className];
        }
        
        if(isset($this->bindings[$className])) {
            return $this->make($this->bindings[$className]);
        } else {

            if (isset($this->building[$className])) {
                throw new ContainerException("Circular dependency detected: {$className}");
            }
            
            $this->building[$className] = true;
            
            $reflectionClass = new ReflectionClass($className);
            if (!$reflectionClass->isInstantiable()) {
                throw new ContainerException(
                    "Cannot instantiate {$className}. Is it an interface or abstract class? Did you forget to bind it?"
                );
            }

            $constructor = $reflectionClass->getConstructor();
            if ($constructor === null) {
                unset($this->building[$className]);
                return new $className;
            }

            $parameters = $constructor->getParameters();
            $args = [];

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();

                if ($type === null) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $args[] = $parameter->getDefaultValue();
                        continue;
                    }
                    throw new ContainerException(
                        "Parameter \${$parameter->getName()} in {$className} has no type hint and no default value."
                    );
                }

                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $args[] = $this->make($type->getName());
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve primitive parameter \${$parameter->getName()} in {$className}. Register it as a singleton with explicit values."
                    );
                }
            }
            unset($this->building[$className]);
            return $reflectionClass->newInstanceArgs($args);
        }
    }
}