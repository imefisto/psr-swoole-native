<?php
namespace Imefisto\PsrSwoole;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookies;

class ResponseMerger
{
    public const FSTAT_MODE_S_IFIFO = 0010000;
    public const BUFFER_SIZE = 8192;

    protected const FILES_STREAM_TYPE = 'STDIO';
    protected const FILES_WRAPPER_TYPE = 'plainfile';

    public function __construct(private readonly ?int $autoChunkSize = null)
    {
    }

    public function toSwoole(ResponseInterface $psrResponse, Response $swooleResponse): Response
    {
        $swooleResponse->status($psrResponse->getStatusCode());
        $this->copyHeaders($psrResponse, $swooleResponse);
        $this->copyBody($psrResponse, $swooleResponse);

        return $swooleResponse;
    }

    private function copyHeaders($psrResponse, $swooleResponse)
    {
        $this->setCookies($swooleResponse, $psrResponse);

        $psrResponse = $psrResponse->withoutHeader('Set-Cookie');

        foreach ($psrResponse->getHeaders() as $key => $headerArray) {
            $swooleResponse->header($key, implode('; ', $headerArray));
        }
    }

    private function setCookies($swooleResponse, $psrResponse)
    {
        if (!$psrResponse->hasHeader('Set-Cookie')) {
            return;
        }

        $setCookies = SetCookies::fromSetCookieStrings($psrResponse->getHeader('Set-Cookie'));
        foreach ($setCookies->getAll() as $setCookie) {
            $swooleResponse->cookie(
                $setCookie->getName(),
                $setCookie->getValue() ?? '',
                $setCookie->getExpires(),
                $setCookie->getPath() ?? '/',
                $setCookie->getDomain() ?? '',
                $setCookie->getSecure(),
                $setCookie->getHttpOnly(),
                $this->getSameSiteModifier($setCookie)
            );
        }
    }

    private function getSameSiteModifier($setCookie)
    {
        $sameSite = $setCookie->getSameSite() ?? SameSite::lax();

        return explode('=', strtolower((string) $sameSite->asString()))[1];
    }

    private function copyBody($psrResponse, $swooleResponse)
    {
        $resource = $psrResponse->getBody()->detach();

        if (!is_resource($resource)) {
            return;
        }

        if (false !== $uri = $this->getUriIfFileStream($resource)) {
            $swooleResponse->sendfile($uri);
            return;
        }

        if ($this->isPipe($resource)) {
            $this->handlePipedResource($resource, $swooleResponse);
            return;
        }

        if (stream_get_meta_data($resource)['seekable']) {
            rewind($resource);
        }

        if ($this->autoChunkSize === null) {
            $swooleResponse->end(stream_get_contents($resource));
            return;
        }

        while (!feof($resource)) {
            $chunk = fread($resource, $this->autoChunkSize);
            if ($chunk !== '') {
                $swooleResponse->write($chunk);
            }
        }

        $swooleResponse->end();
    }

    private function getUriIfFileStream($resource): string|bool
    {
        $metadata = stream_get_meta_data($resource);
        $streamType = explode('/', $metadata['stream_type'])[0];
        $wrapperType = explode('/', $metadata['wrapper_type'] ?? '')[0];

        return
            $streamType === static::FILES_STREAM_TYPE &&
            $wrapperType === static::FILES_WRAPPER_TYPE
            ? $metadata['uri']
            : false
            ;
    }

    private function isPipe($resource)
    {
        $stat = fstat($resource);
        return ($stat['mode'] & self::FSTAT_MODE_S_IFIFO) === self::FSTAT_MODE_S_IFIFO;
    }

    private function handlePipedResource($resource, $swooleResponse)
    {
        while (!feof($resource)) {
            $buff = fread($resource, self::BUFFER_SIZE);
            !empty($buff) && $swooleResponse->write($buff);
        }
        pclose($resource);
    }
}
