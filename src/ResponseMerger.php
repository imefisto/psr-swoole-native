<?php
namespace Imefisto\PsrSwoole;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;

class ResponseMerger
{
    public const FSTAT_MODE_S_IFIFO = 0010000;
    public const BUFFER_SIZE = 8192;

    protected const FILES_STREAM_TYPE = 'STDIO';
    protected const FILES_WRAPPER_TYPE = 'plainfile';

    public function toSwoole(ResponseInterface $psrResponse, Response $swooleResponse): Response
    {
        $swooleResponse->status($psrResponse->getStatusCode());
        $this->copyHeaders($psrResponse, $swooleResponse);
        $this->copyBody($psrResponse, $swooleResponse);

        return $swooleResponse;
    }

    private function copyHeaders($psrResponse, $swooleResponse)
    {
        if (empty($psrResponse->getHeaders())) {
            return;
        }

        $this->setCookies($swooleResponse, $psrResponse);

        $psrResponse = $psrResponse->withoutHeader('Set-Cookie');
        
        foreach ($psrResponse->getHeaders() as $key => $headerArray) {
            $swooleResponse->header($key, implode(', ', $headerArray));
        }
    }

    private function setCookies($swooleResponse, $psrResponse)
    {
        if (!$psrResponse->hasHeader('Set-Cookie')) {
            return;
        }

        foreach ($psrResponse->getHeader('Set-Cookie') as $setCookieString) {
            $setCookie = SetCookie::fromSetCookieString($setCookieString);
            $swooleResponse->cookie(
                $setCookie->getName(),
                $setCookie->getValue() ?? '',
                $this->getExpires($setCookie, $setCookieString),
                $setCookie->getPath() ?? '/',
                $setCookie->getDomain() ?? '',
                $setCookie->getSecure(),
                $setCookie->getHttpOnly(),
                $this->getSameSiteModifier($setCookie)
            );
        }
    }

    /**
     * Max-Age takes precedence over Expires (RFC 6265, section 5.3).
     * FigCookies returns 0 both for a missing Max-Age and for Max-Age=0,
     * so presence is detected on the raw header.
     */
    private function getExpires(SetCookie $setCookie, string $setCookieString): int
    {
        if (preg_match('/;\s*max-age\s*=\s*(-?\d+)/i', $setCookieString, $matches) !== 1) {
            return $setCookie->getExpires();
        }

        $maxAge = (int) $matches[1];

        // 1 instead of 0: Swoole reads 0 as "session cookie"
        return $maxAge > 0 ? time() + $maxAge : 1;
    }

    private function getSameSiteModifier($setCookie)
    {
        $sameSite = $setCookie->getSameSite() ?? SameSite::lax();

        return explode('=', strtolower((string) $sameSite->asString()))[1];
    }

    private function copyBody($psrResponse, $swooleResponse)
    {
        if ($this->isFileStreamInBody($psrResponse)) {
            $swooleResponse->sendfile($psrResponse->getBody()->getMetadata('uri'));
            return;
        }

        if ($psrResponse->getBody()->getSize() == 0) {
            $this->copyBodyIfIsAPipe($psrResponse, $swooleResponse);
            return;
        }

        if ($psrResponse->getBody()->isSeekable()) {
            $psrResponse->getBody()->rewind();
        }

        $swooleResponse->write($psrResponse->getBody()->getContents());
    }

    private function copyBodyIfIsAPipe($psrResponse, $swooleResponse)
    {
        $resource = $psrResponse->getBody()->detach();

        if (!is_resource($resource)) {
            return;
        }

        if ($this->isPipe($resource)) {
            while (!feof($resource)) {
                $buff = fread($resource, self::BUFFER_SIZE);
                !empty($buff) && $swooleResponse->write($buff);
            }
            pclose($resource);
        }
    }

    private function isPipe($resource)
    {
        $stat = fstat($resource);
        return ($stat['mode'] & self::FSTAT_MODE_S_IFIFO) === self::FSTAT_MODE_S_IFIFO;
    }

    private function isFileStreamInBody(ResponseInterface $psrResponse): bool
    {
        $streamType = explode('/', (string) $psrResponse->getBody()->getMetadata('stream_type'))[0] ?? '';
        $wrapperType = explode('/', (string) $psrResponse->getBody()->getMetadata('wrapper_type'))[0] ?? '';

        return
            $streamType === static::FILES_STREAM_TYPE &&
            $wrapperType === static::FILES_WRAPPER_TYPE &&
            is_string($psrResponse->getBody()->getMetadata('uri'));
    }
}
