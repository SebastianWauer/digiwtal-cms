<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Router mit statischen Routen und Parameter-Segmenten.
 *
 * Statische Routen (bisherige Nutzung, unverändert):
 *   $router->get('/users', UsersController::class, 'index');
 *
 * Parameter-Routen (neue Syntax mit {name} oder {name:constraint}):
 *   $router->get('/users/{id}',          UsersController::class, 'show');
 *   $router->get('/users/{id:\d+}',      UsersController::class, 'show');
 *   $router->get('/pages/{slug:.+}',     PagesController::class, 'show');  // matcht auch /pages/a/b/c
 *
 * Nach dem Dispatch sind gematchte Parameter abrufbar via:
 *   $router->params()  // z.B. ['id' => '42', 'slug' => 'blog/post']
 */
final class Router
{
    /**
     * Exakte (statische) Routen: [method][path] => [class, fn]
     * @var array<string, array<string, array{0:class-string,1:string}>>
     */
    private array $staticRoutes = ['GET' => [], 'POST' => []];

    /**
     * Parameter-Routen: [method][] => [regex, paramNames[], class, fn]
     * @var array<string, list<array{regex:string, names:list<string>, class:class-string, fn:string}>>
     */
    private array $paramRoutes = ['GET' => [], 'POST' => []];

    /** @var array<string, string> */
    private array $matchedParams = [];

    // ---------------------------------------------------------------
    // Öffentliche API (unverändert + abwärtskompatibel)
    // ---------------------------------------------------------------

    public function get(string $path, string $controllerClass, string $method): void
    {
        $this->register('GET', $path, $controllerClass, $method);
    }

    public function post(string $path, string $controllerClass, string $method): void
    {
        $this->register('POST', $path, $controllerClass, $method);
    }

    /** Liefert die gematchten URL-Parameter nach dispatch(). */
    public function params(): array
    {
        return $this->matchedParams;
    }

    // ---------------------------------------------------------------
    // Dispatch
    // ---------------------------------------------------------------

    public function dispatch(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!isset($this->staticRoutes[$method])) {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') $path = '/';
        $path = $this->norm($path);

        // 1) Statische Routen haben Vorrang
        $target = $this->staticRoutes[$method][$path] ?? null;

        if ($target !== null) {
            $this->invoke($target[0], $target[1]);
            return;
        }

        // 2) Parameter-Routen in Registrierungsreihenfolge
        foreach ($this->paramRoutes[$method] as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            // Benannte Gruppen aus preg_match extrahieren
            $params = [];
            foreach ($route['names'] as $name) {
                $params[$name] = $m[$name] ?? '';
            }
            $this->matchedParams = $params;
            $this->invoke($route['class'], $route['fn']);
            return;
        }

        http_response_code(404);
        echo 'Not Found';
    }

    // ---------------------------------------------------------------
    // Interna
    // ---------------------------------------------------------------

    private function register(string $method, string $path, string $class, string $fn): void
    {
        $path = $this->norm($path);

        if (!str_contains($path, '{')) {
            // Statische Route
            $this->staticRoutes[$method][$path] = [$class, $fn];
            return;
        }

        // Parameter-Route: {name} oder {name:constraint}
        [$regex, $names] = $this->compile($path);
        $this->paramRoutes[$method][] = [
            'regex' => $regex,
            'names' => $names,
            'class' => $class,
            'fn'    => $fn,
        ];
    }

    /**
     * Kompiliert z.B. /users/{id:\d+} zu einem benannten Regex.
     * Default-Constraint für {name} ohne Angabe: [^/]+
     *
     * Vorgehen: Pfad an {…}-Platzhaltern splitten, statische Teile
     * per preg_quote escapen, Parameter-Segmente in (?P<name>constraint) übersetzen.
     *
     * @return array{0:string, 1:list<string>}  [regex, paramNames]
     */
    private function compile(string $path): array
    {
        $names  = [];
        $regex  = '';
        $parts  = preg_split('/(\{[^}]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if ($part === '') continue;

            if ($part[0] === '{') {
                // Parameter-Segment: {name} oder {name:constraint}
                $inner    = substr($part, 1, -1);
                $colonPos = strpos($inner, ':');
                if ($colonPos !== false) {
                    $name       = substr($inner, 0, $colonPos);
                    $constraint = substr($inner, $colonPos + 1);
                } else {
                    $name       = $inner;
                    $constraint = '[^/]+';
                }
                $names[] = $name;
                $regex  .= '(?P<' . $name . '>' . $constraint . ')';
            } else {
                // Statisches Segment – escapen
                $regex .= preg_quote($part, '#');
            }
        }

        return ['#^' . $regex . '$#', $names];
    }

    private function norm(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') $path = rtrim($path, '/');
        return $path;
    }

    /** @param class-string $class */
    private function invoke(string $class, string $fn): void
    {
        if (!class_exists($class)) {
            http_response_code(500);
            echo 'Controller not found: ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $fn)) {
            http_response_code(500);
            echo 'Controller method not found: ' . htmlspecialchars($fn, ENT_QUOTES, 'UTF-8');
            return;
        }

        $controller->{$fn}();
    }
}
