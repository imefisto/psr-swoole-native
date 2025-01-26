<?php
namespace Imefisto\PsrSwoole;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Swoole\Http\Request as SwooleRequest;

class Request implements RequestInterface
{
    private StreamInterface $body;
    private ?array $headers = null;
    private string $method;
    private string $protocol = '1.1';
    private string $requestTarget;
    private UriInterface $uri;

    public function __construct(
        public readonly SwooleRequest $swooleRequest,
        protected readonly UriFactoryInterface $uriFactory,
        protected readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function getRequestTarget(): string
    {
        return !empty($this->requestTarget)
            ? $this->requestTarget
            : ($this->requestTarget = $this->buildRequestTarget())
            ;
    }

    private function buildRequestTarget()
    {
        $queryString = !empty($this->swooleRequest->server['query_string'])
            ? '?' . $this->swooleRequest->server['query_string']
            : ''
            ;

        return $this->swooleRequest->server['request_uri']
            . $queryString;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return !empty($this->method)
            ? $this->method
            : ($this->method = $this->swooleRequest->server['request_method'])
            ;
    }

    public function withMethod(string $method): RequestInterface
    {
        $validMethods = ['options','get','head','post','put','delete','trace','connect'];
        if (!in_array(strtolower($method), $validMethods)) {
            throw new \InvalidArgumentException('Invalid HTTP method');
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri(): UriInterface
    {
        if (!empty($this->uri)) {
            return $this->uri;
        }

        $userInfo = $this->parseUserInfo() ?? null;

        $host = $this->swooleRequest->header['host'];
        if (!str_contains((string) $this->swooleRequest->header['host'], ':')) {
            $host .= ':80';
        }

        $uri = '//' . (!empty($userInfo) ? $userInfo . '@' : '')
            . $host
            . $this->getRequestTarget()
            ;

        return $this->uri = $this->uriFactory->createUri(
            $uri
        );
    }

    private function parseUserInfo()
    {
        $authorization = $this->swooleRequest->header['authorization'] ?? '';

        if (str_starts_with((string) $authorization, 'Basic')) {
            $parts = explode(' ', (string) $authorization);
            return base64_decode($parts[1]);
        }

        return null;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;

        return $new->shouldUpdateHostHeader($preserveHost)
            ? $new->withHeader('host', $uri->getHost())
            : $new
            ;
    }

    private function shouldUpdateHostHeader($preserveHost)
    {
        return !empty($this->uri->getHost())
            && (!$preserveHost || !$this->hasHeader('host'));
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        $headers = is_array($this->headers)
            ? $this->headers
            : $this->swooleRequest->header;
        return array_map(fn($value) => is_array($value) ? $value : [$value], $headers);
    }

    public function hasHeader($name): bool
    {
        $this->initHeadersList();

        foreach ($this->headers as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return true;
            }
        }
        
        return false;
    }

    private function initHeadersList()
    {
        if (is_array($this->headers)) {
            return;
        }

        $this->headers = $this->swooleRequest->header;
    }

    public function getHeader(string $name): array
    {
        if (!$this->hasHeader($name)) {
            return [];
        }

        foreach ($this->headers as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return is_array($value)
                    ? $value
                    : [$value]
                    ;
            }
        }
    }
    
    public function getHeaderLine(string $name): string
    {
        return \implode(',', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        $new = clone $this;
        $new->initHeadersList();

        $new->headers[$name] = $value;

        return $new;
    }

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        $new = clone $this;

        if (is_array($new->headers[$name])) {
            $new->headers[$name][] = $value;
        } else {
            $new->headers[$name] = [
                $new->headers[$name],
                $value
            ];
        }

        return $new;
    }

    public function withoutHeader(string $name): MessageInterface
    {
        $new = clone $this;

        if (!$new->hasHeader($name)) {
            return $new;
        }

        foreach ($new->headers as $key => $value) {
            if (strtolower($name) == $key) {
                unset($new->headers[$key]);
                return $new;
            }
        }
    }

    public function getBody(): StreamInterface
    {
        return $this->body ?? $this->streamFactory->createStream($this->swooleRequest->rawContent());
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }
}
