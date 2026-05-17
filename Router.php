<?php
/**
 * Router.php
 * -----------
 * Maps URLs to Controller@method pairs.
 * Supports GET / POST routes and named URL parameters (:id, :slug).
 *
 * Author : Mounir Bekkar
 */

class Router
{
    private array $routes = [];

    // ── Registration ──────────────────────────────────────────────────────────

    public function get(string $uri, string $action): void
    {
        $this->add('GET', $uri, $action);
    }

    public function post(string $uri, string $action): void
    {
        $this->add('POST', $uri, $action);
    }

    private function add(string $method, string $uri, string $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'uri'    => $uri,
            'action' => $action,
        ];
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    /**
     * Match the current request against registered routes and call the handler.
     * Throws a 404 if no route matches.
     */
    public function dispatch(string $requestUri, string $requestMethod): void
    {
        // Strip query string
        $uri = strtok($requestUri, '?');
        // Remove base path (useful when app lives in a sub-folder)
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/') {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($requestMethod)) {
                continue;
            }

            $params = $this->matchUri($route['uri'], $uri);
            if ($params !== false) {
                $this->call($route['action'], $params);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/../app/Views/404.php';
    }

    /**
     * Convert a route pattern like /recipes/:id to a regex.
     * Returns an array of matched params or false.
     */
    private function matchUri(string $pattern, string $uri): array|false
    {
        // Escape slashes, replace :param with named capture group
        $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Keep only string keys (named captures)
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Instantiate the controller and call the method.
     * Action format: "ControllerClass@methodName"
     */
    private function call(string $action, array $params): void
    {
        [$controllerClass, $method] = explode('@', $action);

        $controllerFile = __DIR__ . "/../app/Controllers/{$controllerClass}.php";
        if (!file_exists($controllerFile)) {
            throw new \RuntimeException("Controller not found: {$controllerClass}");
        }

        require_once $controllerFile;
        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Method {$method} not found in {$controllerClass}");
        }

        $controller->$method($params);
    }
}
