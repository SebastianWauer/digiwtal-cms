<?php
declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = ['method' => strtoupper($method), 'path' => $path, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            // Replace all {param} placeholders with (\d+) pattern
            $pattern = preg_replace('/\{[a-zA-Z_]+\}/', '(\\d+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove full match
                
                // CAST NUMERIC STRINGS TO INT
                $args = [];
                foreach ($matches as $value) {
                    if (is_string($value) && ctype_digit($value)) {
                        $args[] = (int)$value;
                    } else {
                        $args[] = $value;
                    }
                }
                
                call_user_func_array($route['handler'], $args);
                return;
            }
        }

        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
    }
}
