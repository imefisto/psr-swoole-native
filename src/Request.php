<?php
namespace Inek\PsrSwoole;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Swoole\Http\Request as SwooleRequest;

class Request implements RequestInterface
{
    public function __construct(
        SwooleRequest $swooleRequest,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->swooleRequest = $swooleRequest;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
    }

    public function getRequestTarget()
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

    public function withRequestTarget($requestTarget)
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod()
    {
        return !empty($this->method)
            ? $this->method
            : ($this->method = $this->swooleRequest->server['request_method'])
            ;
    }

    public function withMethod($method)
    {
        $validMethods = ['options','get','head','post','put','delete','trace','connect'];
        if (!in_array(strtolower($method), $validMethods)) {
            throw new \InvalidArgumentException('Invalid HTTP method');
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri()
    {
        if (!empty($this->uri)) {
            return $this->uri;
        }

        $userInfo = $this->parseUserInfo() ?? null;

        $uri = (!empty($userInfo) ? '//' . $userInfo . '@' : '')
            . $this->swooleRequest->header['host']
            . $this->getRequestTarget()
            ;

        return $this->uri = $this->uriFactory->createUri(
            $uri
        );
    }

    private function parseUserInfo()
    {
        $authorization = $this->swooleRequest->header['authorization'] ?? '';

        if (strpos($authorization, 'Basic') === 0) {
            $parts = explode(' ', $authorization);
            return base64_decode($parts[1]);
        }

        return null;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $new = clone $this;
        $new->uri = $uri;
        return $new;
    }

    public function getProtocolVersion()
    {
        return $this->protocol ?? ($this->protocol = '1.1');
    }

    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    public function getHeaders()
    {
        return $this->swooleRequest->header;
    }

    public function hasHeader($name)
    {
        foreach ($this->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return true;
            }
        }
        
        return false;
    }

    public function getHeader($name)
    {
        if (!$this->hasHeader($name)) {
            return [];
        }

        foreach ($this->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return is_array($value)
                    ? $value
                    : [$value]
                    ;
            }
        }
    }
    
    public function getHeaderLine($name)
    {
        foreach ($this->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return implode(',', $value);
            }
        }

        return '';
    }

    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->swooleRequest->header[$name] = $value;
        return $new;
    }

    public function withAddedHeader($name, $value)
    {
        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        $new = clone $this;
        if (is_array($new->swooleRequest->header[$name])) {
            $new->swooleRequest->header[$name][] = $value;
        } else {
            $new->swooleRequest->header[$name] = [
                $new->swooleRequest->header[$name],
                $value
            ];
        }

        return $new;
    }

    public function withoutHeader($name)
    {
        $new = clone $this;

        if (!$new->hasHeader($name)) {
            return $new;
        }

        foreach ($new->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                unset($new->swooleRequest->header[$key]);
                return $new;
            }
        }
    }

    public function getBody()
    {
        return $this->body ?? $this->streamFactory->createStream($this->swooleRequest->rawContent());
    }

    public function withBody(StreamInterface $body)
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }
}
