<?php declare(strict_types=1);

namespace Bref\DevServer;


use Illuminate\Http\JsonResponse;

/**
 * @internal
 */
class ResponseEmitter
{
    public function emit(JsonResponse $response): void
    {
        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        echo $response->getData();
    }

    private function emitStatusLine(JsonResponse $response): void
    {
        $reasonPhrase = $response->statusText();
        $statusCode = $response->getStatusCode();

        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            ($reasonPhrase ? ' ' . $reasonPhrase : '')
        ), true, $statusCode);
    }

    private function emitHeaders(JsonResponse $response): void
    {
        $statusCode = $response->getStatusCode();


        foreach ($response->headers as $header => $values) {
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
