<?php
namespace Inek\PsrSwoole\Testing;

use Inek\PsrSwoole\Request;
use Swoole\Http\Request as SwooleRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class RequestTest extends TestCase
{
    /**
     * @test
     */
    public function getRequestTargetOriginForm()
    {
        $uri = '/some-uri';
        $request = $this->buildRequest($uri);
        $this->assertEquals($uri, $request->getRequestTarget());
    }

    /**
     * @test
     */
    public function getRequestTargetOriginFormWithQueryParams()
    {
        $uri = '/';
        $queryParams = 'foo=1&baz=2';
        $request = $this->buildRequest($uri, 'get', $queryParams);
        $this->assertEquals($uri . '?' . $queryParams, $request->getRequestTarget());
    }

    /**
     * @test
     */
    public function withRequestTarget()
    {
        $uri = '/some-uri';
        $request = $this->buildRequest($uri);

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
        $request = $this->buildRequest('/', $method);

        $this->assertEquals($method, $request->getMethod());
    }    

    /**
     * @test
     */
    public function withMethod()
    {
        $request = $this->buildRequest('/');

        $newMethod = 'post';
        $new = $request->withMethod($newMethod);
        $this->assertEquals($newMethod, $new->getMethod());
    }    

    /**
     * @test
     */
    public function withMethodCaseInsensitive()
    {
        $request = $this->buildRequest('/');

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
        $request = $this->buildRequest('/');
        $request->withMethod('FOO');
    }

    /**
     * @test
     */
    public function getUri()
    {
        $request = $this->buildRequest('/foo', 'get', 'foo=1&bar=2', 'someuser:somepass');
        $this->assertInstanceOf(UriInterface::class, $request->getUri());

        $this->assertEquals('/foo', $request->getUri()->getPath());
        $this->assertEquals('foo=1&bar=2', $request->getUri()->getQuery());
        $this->assertEquals('someuser:somepass', $request->getUri()->getUserInfo());
        $this->assertEquals('localhost', $request->getUri()->getHost());
        $this->assertEquals(9501, $request->getUri()->getPort());
    }    

    /**
     * @test
     */
    public function withUri()
    {
        $request = $this->buildRequest('/');
        $newUri = new Uri('/new-uri');
        $new = $request->withUri($newUri);
        $this->assertEquals($newUri, $new->getUri());
    }    

    /**
     * @test
     */
    public function getProtocolVersion()
    {
        $request = $this->buildRequest('/');
        $this->assertEquals('1.1', $request->getProtocolVersion());
    }    

    /**
     * @test
     */
    public function withProtocolVersion()
    {
        $request = $this->buildRequest('/');
        $new = $request->withProtocolVersion('1.0');
        $this->assertEquals('1.0', $new->getProtocolVersion());
    }    

    /**
     * @test
     */
    public function getHeaders()
    {
        $headers = [
            'foo' => 'bar',
        ];

        $request = $this->buildRequest('/', 'get', '', null, $headers);

        $requestHeaders = $request->getHeaders();
        foreach ($headers as $name => $value) {
            $this->assertEquals($value, $requestHeaders[$name]);
        }
    }    

    /**
     * @test
     */
    public function hasHeader()
    {
        $headers = [
            'foo' => 'bar',
        ];

        $request = $this->buildRequest('/', 'get', '', null, $headers);
        $this->assertFalse($request->hasHeader('foox'));
        $this->assertTrue($request->hasHeader('foo'));
        $this->assertTrue($request->hasHeader('Foo'));
    }    

    /**
     * @test
     */
    public function getHeader()
    {
        $headers = [
            'foo' => 'bar',
        ];

        $request = $this->buildRequest('/', 'get', '', null, $headers);
        $this->assertEquals(['bar'], $request->getHeader('foo'));
        $this->assertEquals(['bar'], $request->getHeader('Foo'));

        $this->assertEquals([], $request->getHeader('foo2'));
    }

    /**
     * @test
     */
    public function getHeaderLine()
    {
        $headers = [
            'foo' => ['bar', 'bar2'],
        ];

        $request = $this->buildRequest('/', 'get', '', null, $headers);
        $this->assertEquals('bar,bar2', $request->getHeaderLine('foo'));
        $this->assertEquals('bar,bar2', $request->getHeaderLine('Foo'));
        $this->assertEquals('', $request->getHeaderLine('foox'));
    }    

    /**
     * @test
     */
    public function withHeader()
    {
        $request = $this->buildRequest('/');
        $new = $request->withHeader('foo', 'bar');
        $this->assertTrue($new->hasHeader('foo'));
        $this->assertTrue($new->hasHeader('Foo'));
        $this->assertEquals(['bar'], $new->getHeader('foo'));
        $this->assertEquals(['bar'], $new->getHeader('Foo'));
    }    

    /**
     * @test
     */
    public function withAddedHeader()
    {
        $request = $this->buildRequest('/')
                        ->withAddedHeader('foo', 'bar');
        $this->assertEquals(['bar'], $request->getHeader('foo'));

        $new = $request->withAddedHeader('foo', 'bar2');
        $this->assertEquals(['bar', 'bar2'], $new->getHeader('foo'));
    }    

    /**
     * @test
     */
    public function withoutHeader()
    {
        $headers = [
            'foo' => ['bar', 'bar2'],
        ];

        $request = $this->buildRequest('/', 'get', '', null, $headers);
        $new = $request->withoutHeader('foo');
        $this->assertFalse($new->hasHeader('foo'));
    }    

    /**
     * @test
     */
    public function withoutHeaderCaseInsensitive()
    {
        $headers = [
            'foo' => ['bar', 'bar2'],
        ];

        $request = $this->buildRequest('/', 'get', '', null, $headers);
        $new = $request->withoutHeader('fOo');
        $this->assertFalse($new->hasHeader('foo'));
    }    

    public function getBody()
    {
    }    

    public function withBody()
    {
    }    

    private function buildRequest($uri, $method = 'get', $queryString = '', $userInfo = null, $headers = [])
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->getMock();
        $swooleRequest->server = [
            'request_method' => $method,
            'request_uri' => $uri,
            'server_protocol' => 'HTTP/1.1',
        ];
        $swooleRequest->header = [
            'host' => 'localhost:9501'
        ];

        if (!empty($queryString)) {
            $swooleRequest->server['query_string'] = $queryString;
        }

        if (!empty($userInfo)) {
            $swooleRequest->header['authorization'] = 'Basic ' . base64_encode($userInfo);
        }

        $swooleRequest->header = array_merge(
            $swooleRequest->header,
            $headers
        );

        return new Request(
            $swooleRequest,
            new Psr17Factory
        );
    }
}
