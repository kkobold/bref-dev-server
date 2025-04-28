<?php declare(strict_types=1);

namespace Bref\DevServer;


use Nyholm\Psr7\Response;

/**
 * @internal
 */
class ResponseEmitter
{
    public function emit(Response $response): void
    {
        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        echo $response->getBody();
    }

    private function emitStatusLine(Response $response): void
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode = $response->getStatusCode();

        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            ($reasonPhrase ? ' ' . $reasonPhrase : '')
        ), true, $statusCode);
    }

    private function emitHeaders(Response $response): void
    {
        $statusCode = $response->getStatusCode();


        foreach ($response->getHeaders() as $header => $values) {
            $name = $this->filterHeader($header);
            $first = $name !== 'Set-Cookie';
            foreach ($values as $value) {
                header(sprintf(
                    '%s: %s',
                    $name,
                    $value
                ), $first, $statusCode);
                $first = false;
            }
        }
    }

    private function filterHeader(string $header): string
    {
        return ucwords($header, '-');
    }
}
