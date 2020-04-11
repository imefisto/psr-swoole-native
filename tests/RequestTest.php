<?php
namespace Imefisto\PsrSwoole\Testing;

use Imefisto\PsrSwoole\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class RequestTest extends TestCase
{
    use SwooleRequestBuilderTrait;

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
        $queryParams = 'foo=1&baz=2';
        $request = $this->buildRequest();
        $request->swooleRequest->server['query_string'] = $queryParams;
        $this->assertEquals('/?' . $queryParams, $request->getRequestTarget());
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
        $request = $this->buildRequest();

        $newMethod = 'post';
        $new = $request->withMethod($newMethod);
        $this->assertEquals($newMethod, $new->getMethod());
    }

    /**
     * @test
     */
    public function withMethodCaseInsensitive()
    {
        $request = $this->buildRequest();

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
        $request = $this->buildRequest();
        $request->withMethod('FOO');
    }

    /**
     * @test
     */
    public function getUri()
    {
        $request = $this->buildRequest('/foo');
        $queryString = 'foo=1&bar=2';
        $request->swooleRequest->server['query_string'] = $queryString;

        $userInfo = 'someuser:somepass';
        $request->swooleRequest->header['authorization'] = 'Basic ' . base64_encode($userInfo);

        $this->assertInstanceOf(UriInterface::class, $request->getUri());

        $this->assertEquals('/foo', $request->getUri()->getPath());
        $this->assertEquals($queryString, $request->getUri()->getQuery());
        $this->assertEquals($userInfo, $request->getUri()->getUserInfo());
        $this->assertEquals('localhost', $request->getUri()->getHost());
        $this->assertEquals(9501, $request->getUri()->getPort());
    }

    /**
     * @test
     */
    public function withUri()
    {
        $request = $this->buildRequest();
        $newUri = new Uri('/new-uri');
        $new = $request->withUri($newUri);
        $this->assertEquals($newUri, $new->getUri());
    }

    /**
     * @test
     */
    public function getProtocolVersion()
    {
        $request = $this->buildRequest();
        $this->assertEquals('1.1', $request->getProtocolVersion());
    }

    /**
     * @test
     */
    public function withProtocolVersion()
    {
        $request = $this->buildRequest();
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

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

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

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

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

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

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

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

        $this->assertEquals('bar,bar2', $request->getHeaderLine('foo'));
        $this->assertEquals('bar,bar2', $request->getHeaderLine('Foo'));
        $this->assertEquals('', $request->getHeaderLine('foox'));
    }

    /**
     * @test
     */
    public function withHeader()
    {
        $request = $this->buildRequest();
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
        $request = $this->buildRequest()
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

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

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

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

        $new = $request->withoutHeader('fOo');
        $this->assertFalse($new->hasHeader('foo'));
    }

    /**
     * @test
     */
    public function getBody()
    {
        $postBody = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];

        $request = $this->buildRequest('/', 'post', $postBody);
        $request->swooleRequest->post = $postBody;

        $this->assertInstanceOf(StreamInterface::class, $request->getBody());
        $this->assertEquals(http_build_query($postBody), (string) $request->getBody());
    }

    /**
     * @test
     */
    public function withBody()
    {
        $post1 = [
            'foo1' => 'bar1',
        ];

        $post2 = [
            'foo2' => 'bar2',
        ];

        $request = $this->buildRequest('/', 'post', $post1);
        $newStream = Stream::create(http_build_query($post2));
        $new = $request->withBody($newStream);

        $this->assertEquals($newStream, $new->getBody());
    }

    private function buildRequest(
        $uri = '/',
        $method = 'get',
        $postBody = null
    ) {
        return new Request(
            $this->buildSwooleRequest($uri, $method, $postBody),
            new Psr17Factory,
            new Psr17Factory
        );
    }
}
