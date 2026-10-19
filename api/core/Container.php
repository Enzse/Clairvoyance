<?php

namespace Core;

/**
 * Minimal dependency container. Classes are autowired from their constructor type hints
 * and shared for the lifetime of the container (one per request).
 */
class Container
{
    private array $factories = [];
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $instance = isset($this->factories[$id])
            ? ($this->factories[$id])($this)
            : $this->build($id);

        return $this->instances[$id] = $instance;
    }

    private function build(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \LogicException("Cannot autowire \${$param->getName()} of {$class}");
            }
        }
        return $reflection->newInstanceArgs($args);
    }
}
