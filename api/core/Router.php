<?php

namespace Core;

/**
 * Maps "METHOD /path/{param}" to [ControllerClass, 'method']. Path parameters match
 * positive integers (every id in this API is one) and are passed to the handler as ints.
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern);
        $this->routes[] = [strtoupper($method), '#^' . $regex . '$#', $handler];
    }

    /** @return array{0: array, 1: array<string,int>} handler and path parameters */
    public function match(string $method, string $path): array
    {
        $allowed = [];
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($routeMethod !== $method) {
                $allowed[] = $routeMethod;
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = (int)$value;
                }
            }
            return [$handler, $params];
        }

        if ($allowed) {
            throw new HttpException(405, 'Method not allowed');
        }
        throw HttpException::notFound('Endpoint not found');
    }
}
