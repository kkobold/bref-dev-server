<?php declare(strict_types=1);

namespace Bref\DevServer;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
class NotFound
{
    public function handle( $request): Response
    {
        $url = $request->getUri()->getPath();

        return new Response(404, [], "Route '$url' not found in serverless.yml");
    }
}
