<?php
namespace Inek\PsrSwoole\Testing;

use Inek\PsrSwoole\Request;
use Swoole\Http\Request as SwooleRequest;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    /**
     * @test
     */
    public function getRequestTargetOriginForm()
    {
        $uri = '/some-uri';
        $request = new Request($this->buildSwooleRequest($uri));
        $this->assertEquals($uri, $request->getRequestTarget());
    }

    /**
     * @test
     */
    public function getRequestTargetOriginFormWithQueryParams()
    {
        $uri = '/';
        $queryParams = 'foo=1&baz=2';
        $request = new Request($this->buildSwooleRequest($uri, 'get', $queryParams));
        $this->assertEquals($uri . '?' . $queryParams, $request->getRequestTarget());
    }

    /**
     * @test
     */
    public function withRequestTarget()
    {
        $uri = '/some-uri';
        $request = new Request($this->buildSwooleRequest($uri));

        $newTarget = '/some-other-uri';

        $new = $request->withRequestTarget($newTarget);
        $this->assertEquals($newTarget, $new->getRequestTarget());
    }    

    /**
     * @test
     */
    public function getMethod()
    {
        $method = 'get';
        $request = new Request($this->buildSwooleRequest('/', $method));

        $this->assertEquals($method, $request->getMethod());
    }    

    /**
     * @test
     */
    public function withMethod()
    {
        $request = new Request($this->buildSwooleRequest('/'));

        $newMethod = 'post';
        $new = $request->withMethod($newMethod);
        $this->assertEquals($newMethod, $new->getMethod());
    }    

    /**
     * @test
     */
    public function withMethodCaseInsensitive()
    {
        $request = new Request($this->buildSwooleRequest('/'));

        $newMethod = 'POST';
        $new = $request->withMethod($newMethod);
        $this->assertEquals($newMethod, $new->getMethod());
    }    

    /**
     * @test
     */
    public function withMethodThrowsExceptionForInvalidHTTPMethod()
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = new Request($this->buildSwooleRequest('/'));
        $request->withMethod('FOO');
    }

    public function getUri()
    {
    }    

    public function withUri()
    {
    }    

    public function getProtocolVersion()
    {
    }    

    public function withProtocolVersion()
    {
    }    

    public function getHeaders()
    {
    }    

    public function hasHeader()
    {
    }    

    public function getHeaderLine()
    {
    }    

    public function withHeader()
    {
    }    

    public function withAddedHeader()
    {
    }    

    public function withoutHeader()
    {
    }    

    public function getBody()
    {
    }    

    public function withBody()
    {
    }    

    private function buildSwooleRequest($uri, $method = 'get', $queryString = '')
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->getMock();
        $swooleRequest->server = [
            'request_method' => $method,
            'request_uri' => $uri,
        ];

        if (!empty($queryString)) {
            $swooleRequest->server['query_string'] = $queryString;
        }

        return $swooleRequest;
    }
}
