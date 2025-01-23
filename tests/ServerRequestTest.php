<?php
namespace Imefisto\PsrSwoole\Testing;

use PHPUnit\Framework\TestCase;
use Imefisto\PsrSwoole\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\UploadedFileInterface;
use Swoole\Http\Request as SwooleRequest;

/**
 * @covers Imefisto\PsrSwoole\Request
 * @covers Imefisto\PsrSwoole\ServerRequest
 */
class ServerRequestTest extends TestCase
{
    use SwooleRequestBuilderTrait;

    protected $uriFactory;
    protected $streamFactory;
    protected $uploadedFileFactory;

    protected function setUp(): void
    {
        $this->uriFactory = new Psr17Factory;
        $this->streamFactory = new Psr17Factory;
        $this->uploadedFileFactory = new Psr17Factory;
    }

    /**
     * @test
     */
    public function getServerParams()
    {
        $request = $this->buildRequest();

        $definedParamsOnSwooleRequest = [
            'REQUEST_METHOD' => 'get',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ];

        $this->assertEquals(
            array_merge($_SERVER, $definedParamsOnSwooleRequest),
            $request->getServerParams()
        );
    }

    /**
     * @test
     */
    public function getCookieParams()
    {
        $cookies = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];

        $swooleRequest = $this->buildSwooleRequest('/', 'get');
        $swooleRequest->cookie = $cookies;
        $request = $this->buildRequest($swooleRequest);

        $this->assertEquals(count($request->getCookieParams()), count($cookies));
    }

    /**
     * @test
     */
    public function withCookieParams()
    {
        $cookies = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];

        $request = $this->buildRequest();
        $new = $request->withCookieParams($cookies);

        $this->assertEquals(count($new->getCookieParams()), count($cookies));
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function getQueryParams()
    {
        $queryParams = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];

        $request = $this->buildRequest();
        $request->swooleRequest->get = $queryParams;

        $this->assertEquals($queryParams, $request->getQueryParams());
    }

    /**
     * @test
     */
    public function withQueryParams()
    {
        $queryParams = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];

        $request = $this->buildRequest();
        $new = $request->withQueryParams($queryParams);
        $this->assertEquals($queryParams, $new->getQueryParams());
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function getUploadedFiles()
    {
        $swooleRequest = $this->buildSwooleRequest('/', 'post');
        $filepath = __DIR__ . '/dummy.pdf';
        $swooleRequest->files = [
            'name1' => [
                'tmp_name' => $filepath,
                'name' => basename('dummy.pdf'),
                'type' => 'application/pdf',
                'size' => filesize($filepath),
                'error' => 0
            ]
        ];
        $request = $this->buildRequest($swooleRequest);
            
        $this->assertNotEmpty($request->getUploadedFiles());

        foreach ($request->getUploadedFiles() as $file) {
            $this->assertInstanceOf(UploadedFileInterface::class, $file);
            $this->assertEquals('dummy.pdf', $file->getClientFilename());
            $this->assertEquals('application/pdf', $file->getClientMediaType());
            $this->assertEquals(0, $file->getError());
            $this->assertEquals(filesize($filepath), $file->getSize());
        }
    }

    /**
     * @test
     */
    public function getUploadedFilesShouldReturnEmptyArrayIfNoUploads()
    {
        $request = $this->buildRequest();
        $this->assertEmpty($request->getUploadedFiles());
    }

    /**
     * @test
     */
    public function withUploadedFiles()
    {
        $request = $this->buildRequest();
        $filepath = __DIR__ . '/dummy.pdf';
        $new = $request->withUploadedFiles([
            $this->uploadedFileFactory->createUploadedFile(
                $this->streamFactory->createStreamFromFile($filepath),
                filesize($filepath),
                0,
                basename($filepath),
                'application/pdf'
            )
        ]);
            
        $this->assertNotEmpty($new->getUploadedFiles());

        foreach ($new->getUploadedFiles() as $file) {
            $this->assertInstanceOf(UploadedFileInterface::class, $file);
            $this->assertEquals('dummy.pdf', $file->getClientFilename());
            $this->assertEquals('application/pdf', $file->getClientMediaType());
            $this->assertEquals(0, $file->getError());
            $this->assertEquals(filesize($filepath), $file->getSize());
        }

        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function getParsedBodyNull()
    {
        $postBody = [];
        $swooleRequest = $this->buildSwooleRequest('/', 'post', $postBody);
        $request = $this->buildRequest($swooleRequest);
        $this->assertTrue(is_null($request->getParsedBody()));
    }

    /**
     * @test
     */
    public function getParsedBody()
    {
        $postBody = [
            'test' => 1
        ];
        $swooleRequest = $this->buildSwooleRequest('/', 'post', $postBody);
        $request = $this->buildRequest($swooleRequest);
        $this->assertEquals($postBody, $request->getParsedBody());
    }

    /**
     * @test
     */
    public function withParsedBodyWithArrayAsArgument()
    {
        $swooleRequest = $this->buildSwooleRequest('/', 'post', ['test' => 1]);
        $request = $this->buildRequest($swooleRequest);
        $newPost = ['test' => 2];
        $new = $request->withParsedBody($newPost);
        $this->assertEquals($newPost, $new->getParsedBody());
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withParsedBodyWithObjectAsArgument()
    {
        $swooleRequest = $this->buildSwooleRequest('/', 'post', ['test' => 1]);
        $request = $this->buildRequest($swooleRequest);
        $newPost = new \stdclass;
        $newPost->test = 2;
        $new = $request->withParsedBody($newPost);
        $this->assertEquals($newPost, $new->getParsedBody());
    }

    /**
     * @test
     */
    public function withParsedBodyWithNullAsArgument()
    {
        $swooleRequest = $this->buildSwooleRequest('/', 'post', ['test' => 1]);
        $request = $this->buildRequest($swooleRequest);
        $newPost = null;
        $new = $request->withParsedBody($newPost);
        $this->assertEquals($newPost, $new->getParsedBody());
    }

    /**
     * @test
     */
    public function withParsedBodyInvalidArgument()
    {
        $this->expectException(\InvalidArgumentException::class);

        $swooleRequest = $this->buildSwooleRequest('/', 'post', ['test' => 1]);
        $request = $this->buildRequest($swooleRequest);
        $invalidPost = 'test';
        $request->withParsedBody($invalidPost);
    }

    /**
     * @test
     */
    public function getAttributesEmpty()
    {
        $request = $this->buildRequest();
        $this->assertEquals([], $request->getAttributes());
    }

    /**
     * @test
     */
    public function getAttributes()
    {
        $request = $this->buildRequest()->withAttribute('test', 1);
        $this->assertEquals(['test' => 1], $request->getAttributes());
    }

    /**
     * @test
     */
    public function getAttribute()
    {
        $request = $this->buildRequest()->withAttribute('test', 1);
        $this->assertEquals(1, $request->getAttribute('test'));
    }

    /**
     * @test
     */
    public function getAttributeWithDefault()
    {
        $request = $this->buildRequest();
        $this->assertEquals(1, $request->getAttribute('test', 1));
    }

    /**
     * @test
     */
    public function getInexistentAttribute()
    {
        $request = $this->buildRequest();
        $this->assertTrue(is_null($request->getAttribute('test')));
    }

    /**
     * @test
     */
    public function withAttribute()
    {
        $request = $this->buildRequest();
        $new = $request->withAttribute('test', 1);
        $this->assertEquals(['test' => 1], $new->getAttributes());
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withoutAttribute()
    {
        $request = $this->buildRequest()->withAttribute('test', 1);
        $new = $request->withoutAttribute('test');
        $this->assertEquals([], $new->getAttributes());
        $this->assertImmutabililty($request, $new);
    }

    /**
     * @test
     */
    public function withoutAttributeInexistent()
    {
        $request = $this->buildRequest();
        $new = $request->withoutAttribute('test');
        $this->assertEquals([], $new->getAttributes());
    }

    private function buildRequest(SwooleRequest $swooleRequest = null)
    {
        return new ServerRequest(
            $swooleRequest ?? $this->buildSwooleRequest('/', 'get'),
            $this->uriFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );
    }

    private function assertImmutabililty($obj1, $obj2)
    {
        $this->assertNotSame($obj1, $obj2);
    }
}
