<?php
namespace Inek\PsrSwoole;

use Psr\Http\Message\{RequestInterface,UriInterface,StreamInterface};
use Swoole\Http\Request as SwooleRequest;

class Request implements RequestInterface
{
    public function __construct(SwooleRequest $swooleRequest)
    {
        $this->swooleRequest = $swooleRequest;
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
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
    }

    public function getProtocolVersion()
    {
    }

    public function withProtocolVersion($version)
    {
    }

    public function getHeaders()
    {
    }

    public function hasHeader($name)
    {
    }

    public function getHeader($name)
    {
    }
    
    public function getHeaderLine($name)
    {
    }

    public function withHeader($name, $value)
    {
    }

    public function withAddedHeader($name, $value)
    {
    }

    public function withoutHeader($name)
    {
    }

    public function getBody()
    {
    }

    public function withBody(StreamInterface $body)
    {
    }
}
