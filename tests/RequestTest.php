<?php
namespace Imefisto\PsrSwoole\Testing;

use Imefisto\PsrSwoole\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @covers Imefisto\PsrSwoole\Request
 */
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
        $this->assertImmutabililty($request, $new);
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

        $validMethods = ['options', 'get', 'head', 'post', 'put', 'delete', 'trace', 'connect'];

        foreach ($validMethods as $newMethod) {
            $new = $request->withMethod($newMethod);
            $this->assertEquals($newMethod, $new->getMethod());
            $this->assertImmutabililty($request, $new);
        }
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
    public function getUriOnPort80()
    {
        $request = $this->buildRequest('/foo');
        $request->swooleRequest->header = [
            'host' => 'localhost' // when swoole uses port 80, it does not include the port on host
        ];
        $request->swooleRequest->server['server_port'] = 80;

        $this->assertEquals('/foo', $request->getUri()->getPath());
        $this->assertEquals('localhost', $request->getUri()->getHost());
        $this->assertEquals(80, $request->getUri()->getPort());
    }

    /**
     * @test
     */
    public function withUri()
    {
        $request = $this->buildRequest();
        $newUri = new Uri('http://example.com/new-uri');
        $new = $request->withUri($newUri);
        $this->assertEquals($newUri, $new->getUri());
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withUriByDefaultMustSetHostHeaderFromURI()
    {
        $request = $this->buildRequest()->withoutHeader('host');
        $newUri = new Uri('http://example.com/new-uri');
        $new = $request->withUri($newUri);

        $this->assertEquals($newUri->getHost(), $new->getHeader('host')[0]);
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withUriByDefaultMustChangeHostHeaderFromURI()
    {
        $request = $this->buildRequest()->withHeader('host', 'test.com');
        $newUri = new Uri('http://example.com/new-uri');
        $new = $request->withUri($newUri);

        $this->assertEquals($newUri->getHost(), $new->getHeader('host')[0]);
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withUriMustNotUpdateHostHeaderIfUriHasNotHost()
    {
        $host = 'test.com';
        $request = $this->buildRequest()->withHeader('host', $host);
        $newUri = new Uri('/new-uri');
        $new = $request->withUri($newUri);

        $this->assertEquals($host, $new->getHeader('host')[0]);
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withUriWithPreserveHostMustUpdateHostHeaderIfEmpty()
    {
        $request = $this->buildRequest()->withoutHeader('host');
        $newUri = new Uri('http://example.com/new-uri');
        $new = $request->withUri($newUri, true);

        $this->assertEquals($newUri->getHost(), $new->getHeader('host')[0]);
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withUriWithPreserveHostMustNotUpdateHostIfUriHostIsEmpty()
    {
        $request = $this->buildRequest()->withoutHeader('host');
        $newUri = new Uri('/new-uri');
        $new = $request->withUri($newUri, true);

        $this->assertFalse($new->hasHeader('host'));
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withUriWithPreserveHostMustNotUpdateHostHeader()
    {
        $expectedHost = 'test.com';
        $request = $this->buildRequest()->withHeader('host', $expectedHost);
        $newUri = new Uri('http://example.com/new-uri');
        $new = $request->withUri($newUri, true);

        $this->assertEquals(
            $expectedHost,
            $new->getHeader('host')[0]
        );
        $this->assertImmutabililty($request, $new);
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
        $this->assertImmutabililty($request, $new);
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
            $this->assertEquals([$value], $requestHeaders[$name]);
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
            'foo' => 'bar',
        ];

        $request = $this->buildRequest();
        $request->swooleRequest->header = $headers;

        $this->assertEquals('bar', $request->getHeaderLine('foo'));
        $this->assertEquals('bar', $request->getHeaderLine('Foo'));
    }

    /**
     * @test
     */
    public function getHeaderLineWithArray()
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
        $this->assertNotEquals($request->getHeaders(), $new->getHeaders());
    }

    /**
     * @test
     */
    public function withHeaderPreservesPreviousHeaders()
    {
        $request = $this->buildRequest();
        $expectedHeaders = array_keys($request->getHeaders());
        $expectedHeaders[] = 'foo';

        $new = $request->withHeader('foo', 'bar');
        $this->assertEquals(
            $expectedHeaders,
            array_keys($new->getHeaders())
        );
    }

    /**
     * @test
     */
    public function withHeaderPreservesImmutability()
    {
        $request = $this->buildRequest();
        $originalHeaders = $request->getHeaders();
        $new = $request->withHeader('foo', 'bar');

        $this->assertImmutabililty($request, $new);
        $this->assertFalse($request->hasHeader('foo'));
        $this->assertEquals($originalHeaders, $request->getHeaders());
    }

    /**
     * @test
     */
    public function withHeaderMustPreserveCasing()
    {
        $request = $this->buildRequest();
        $new = $request
            ->withHeader('Foo', 'bar')
        ;

        $this->assertTrue(in_array('Foo', array_keys($new->getHeaders())));
        $this->assertEquals(['bar'], $new->getHeader('foo'));
    }

    /**
     * @test
     */
    public function withAddedHeaderSetsNewHeader()
    {
        $request = $this->buildRequest();
        $new = $request->withAddedHeader('foo', 'bar');

        $this->assertEquals(['bar'], $new->getHeader('foo'));
        $this->assertImmutabililty($request, $new);
        $this->assertFalse($request->hasHeader('foo'));
    }

    /**
     * @test
     */
    public function withAddedHeaderAddsToPreviousHeader()
    {
        $request = $this->buildRequest()
                        ->withAddedHeader('foo', 'bar');

        $new = $request->withAddedHeader('foo', 'bar2');
        $this->assertEquals(['bar', 'bar2'], $new->getHeader('foo'));
        $this->assertImmutabililty($request, $new);
        $this->assertEquals(['bar'], $request->getHeader('foo'));
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
        $this->assertImmutabililty($request, $new);
        $this->assertEquals($headers['foo'], $request->getHeader('foo'));
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
        $this->assertImmutabililty($request, $new);
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

    private function assertImmutabililty($obj1, $obj2)
    {
        $this->assertNotSame($obj1, $obj2);
    }
}
