<?php declare(strict_types=1);

namespace Bref\DevServer;

use Psr\Http\Message\ServerRequestInterface;

use function is_array;

/**
 * Reproduces API Gateway routing for local development.
 *
 * @internal
 */
class Router
{
    public static function fromServerlessConfig(array $serverlessConfig): self
    {
        $routes = [];
        foreach ($serverlessConfig['functions'] as $function) {
            $patternA = $function['events'][0]['httpApi'] ?? null;
            $patternB = $function['events'][0]['http'] ?? null;

            if (is_array($patternA)) {
                $pattern = self::patternToString($patternA);
            }elseif (is_array($patternB)) {
                $pattern = self::patternToString($patternB);
            }

            if($pattern)
            $routes[$pattern] = $function['handler'];
            $pattern = false;
        }

        $routes = [];
        foreach ($serverlessConfig['functions'] as $function) {
            $patternA = $function['events'][0]['httpApi'] ?? null;
            $patternB = $function['events'][0]['http'] ?? null;

            if (is_array($patternA)) {
                $pattern = self::patternToString($patternA);
            } elseif (is_array($patternB)) {
                $pattern = self::patternToString($patternB);
            }

            if ($pattern) {
                $routes[] = ['pattern' => $pattern, 'handler' => $function['handler']];
            }
            $pattern = false;
        }

// Ordena rotas mais longas e com parâmetros primeiro
        usort($routes, function ($a, $b) {
            $aPath = explode(' ', $a['pattern'], 2)[1] ?? '';
            $bPath = explode(' ', $b['pattern'], 2)[1] ?? '';
            $lenDiff = strlen($bPath) - strlen($aPath);
            if ($lenDiff !== 0) return $lenDiff;
            $aHasParam = str_contains($aPath, '{');
            $bHasParam = str_contains($bPath, '{');
            if ($aHasParam !== $bHasParam) return $aHasParam ? -1 : 1;
            return 0;
        });

// Converte para array associativo
        $routesAssoc = [];
        foreach ($routes as $route) {
            $routesAssoc[$route['pattern']] = $route['handler'];
        }

        return new self($routesAssoc);
    }

    private static function patternToString(array $pattern): string
    {
        $method = $pattern['method'] ?? '';
        $path = $pattern['path'] ?? '*';
        // Special "any" method MUST be converted to star.
        if ($method === 'any') {
            $method = '*';
        }

        // Alternative catch-all MUST be converted to standard catch-all.
        if ($method === '*' && $path === '*') {
            return '*';
        }

        return $method . ' ' . $path;
    }

    /** @var array<string,string> */
    private array $routes;

    /**
     * @param array<string,string> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @return array{0: ?string, 1: ServerRequestInterface}
     */
    public function match(ServerRequestInterface $request): array
    {
        foreach ($this->routes as $pattern => $handler) {
            // Catch-all
            if ($pattern === '*') return [$handler, $request];

            [$httpMethod, $pathPattern] = explode(' ', $pattern);
            if ($this->matchesMethod($request, $httpMethod) && $this->matchesPath($request, $pathPattern)) {
                $request = $this->addPathParameters($request, $pathPattern);

                return [$handler, $request];
            }
        }

        // No route matched
        return [null, $request];
    }

    private function matchesMethod(ServerRequestInterface $request, string $method): bool
    {
        $method = strtolower($method);

        return ($method === '*') || ($method === strtolower($request->getMethod()));
    }

    private function matchesPath(ServerRequestInterface $request, string $pathPattern): bool
    {
        $requestPath = $request->getUri()->getPath();

        // No path parameter
        if (! str_contains($pathPattern, '{')) {
            return $requestPath === $pathPattern;
        }

        $pathRegex = $this->patternToRegex($pathPattern);

        return preg_match($pathRegex, $requestPath) === 1;
    }

    private function addPathParameters(ServerRequestInterface $request, mixed $pathPattern): ServerRequestInterface
    {
        $requestPath = $request->getUri()->getPath();

        // No path parameter
        if (! str_contains($pathPattern, '{')) {
            return $request;
        }

        $pathRegex = $this->patternToRegex($pathPattern);
        preg_match($pathRegex, $requestPath, $matches);
        foreach ($matches as $name => $value) {
            if (is_string($name)) { // <-- Adicione esta verificação
                $request = $request->withAttribute($name, $value);
            }
        }

        return $request;
    }

    private function patternToRegex(string $pathPattern): string
    {
        // Substitui todos os {param} por grupos nomeados (?<param>[^/]+)
        $regex = preg_replace_callback('/{([^}]+)}/', function ($matches) {
            return '(?<' . $matches[1] . '>[^/]+)';
        }, $pathPattern);

        return '#^' . $regex . '$#';
    }
}
