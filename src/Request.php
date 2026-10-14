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
    private string $protocol;
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
        $validMethods = ['options','get','head','post','put','patch','delete','trace','connect'];
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

        $host = (string) ($this->swooleRequest->header['host'] ?? '');
        if ($host === '') {
            return $this->uri = $this->uriFactory->createUri($this->getRequestTarget());
        }

        if (!str_contains($host, ':')) {
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
        return $this->protocol ??= $this->parseProtocolVersion();
    }

    private function parseProtocolVersion(): string
    {
        $serverProtocol = (string) ($this->swooleRequest->server['server_protocol'] ?? '');

        return str_starts_with($serverProtocol, 'HTTP/')
            ? substr($serverProtocol, 5)
            : '1.1'
            ;
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return array_map(fn($value) => is_array($value) ? $value : [$value], $this->headerList());
    }

    public function hasHeader(string $name): bool
    {
        return $this->findHeaderKey($name) !== null;
    }

    public function getHeader(string $name): array
    {
        $key = $this->findHeaderKey($name);
        if ($key === null) {
            return [];
        }

        $value = $this->headerList()[$key];
        return is_array($value) ? $value : [$value];
    }

    public function getHeaderLine(string $name): string
    {
        return \implode(',', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        $new = $this->withoutHeader($name);
        $new->headers[$name] = $value;

        return $new;
    }

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $key = $this->findHeaderKey($name);
        if ($key === null) {
            return $this->withHeader($name, $value);
        }

        $new = clone $this;
        $new->headers = $this->headerList();
        $new->headers[$key] = array_merge(
            $this->getHeader($key),
            is_array($value) ? $value : [$value]
        );

        return $new;
    }

    public function withoutHeader(string $name): MessageInterface
    {
        $new = clone $this;
        $new->headers = $this->headerList();

        $key = $this->findHeaderKey($name);
        if ($key !== null) {
            unset($new->headers[$key]);
        }

        return $new;
    }

    /**
     * Headers modified through with*() calls, or the native Swoole headers
     * when the request was not modified.
     */
    private function headerList(): array
    {
        return $this->headers ?? $this->swooleRequest->header ?? [];
    }

    private function findHeaderKey(string $name): ?string
    {
        $name = strtolower($name);
        foreach (array_keys($this->headerList()) as $key) {
            if (strtolower((string) $key) === $name) {
                return (string) $key;
            }
        }

        return null;
    }

    public function getBody(): StreamInterface
    {
        return $this->body ??= $this->streamFactory->createStream(
            (string) $this->swooleRequest->rawContent()
        );
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }
}
