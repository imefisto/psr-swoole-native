<?php
namespace Imefisto\PsrSwoole\Testing;

use PHPUnit\Framework\TestCase;
use Imefisto\PsrSwoole\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\UploadedFileInterface;

class ServerRequestTest extends TestCase
{
    use SwooleRequestBuilderTrait;

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
        $this->assertEquals($_SERVER, $request->getServerParams());
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

        $request = $this->buildRequest();
        $request->swooleRequest->cookie = $cookies;

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
    }

    /**
     * @test
     */
    public function getUploadedFiles()
    {
        $request = $this->buildRequest('/', 'post');
        $filepath = __DIR__ . '/dummy.pdf';
        $request->swooleRequest->files = [
            'name1' => [
                'tmp_name' => $filepath,
                'name' => basename('dummy.pdf'),
                'type' => 'application/pdf',
                'size' => filesize($filepath),
                'error' => 0
            ]
        ];
            
        $this->assertNotEmpty($request->getUploadedFiles());

        foreach ($request->getUploadedFiles() as $file) {
            $this->assertInstanceOf(UploadedFileInterface::class, $file);
            $this->assertEquals($file->getClientFilename(), 'dummy.pdf');
            $this->assertEquals($file->getClientMediaType(), 'application/pdf');
            $this->assertEquals($file->getError(), 0);
            $this->assertEquals($file->getSize(), filesize($filepath));
        }
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
            $this->assertEquals($file->getClientFilename(), 'dummy.pdf');
            $this->assertEquals($file->getClientMediaType(), 'application/pdf');
            $this->assertEquals($file->getError(), 0);
            $this->assertEquals($file->getSize(), filesize($filepath));
        }
    }

    /**
     * @test
     */
    public function getParsedBodyNull()
    {
        $request = $this->buildRequest('/', 'post');
        $request->swooleRequest->post = [];
        $this->assertTrue(is_null($request->getParsedBody()));
    }

    /**
     * @test
     */
    public function getParsedBody()
    {
        $request = $this->buildRequest('/', 'post');
        $request->swooleRequest->post = [
            'test' => 1
        ];
        $this->assertEquals($request->swooleRequest->post, $request->getParsedBody());
    }

    /**
     * @test
     */
    public function withParsedBodyWithArrayAsArgument()
    {
        $request = $this->buildRequest('/', 'post');
        $request->swooleRequest->post = [
            'test' => 1
        ];
        $newPost = ['test' => 2];
        $new = $request->withParsedBody($newPost);
        $this->assertEquals($newPost, $new->getParsedBody());
    }

    /**
     * @test
     */
    public function withParsedBodyWithObjectAsArgument()
    {
        $request = $this->buildRequest('/', 'post');
        $request->swooleRequest->post = [
            'test' => 1
        ];
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
        $request = $this->buildRequest('/', 'post');
        $request->swooleRequest->post = [
            'test' => 1
        ];
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

        $request = $this->buildRequest('/', 'post');
        $request->swooleRequest->post = [
            'test' => 1
        ];
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
    public function withoutAttribute()
    {
        $request = $this->buildRequest()->withAttribute('test', 1);
        $new = $request->withoutAttribute('test');
        $this->assertEquals([], $new->getAttributes());
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

    private function buildRequest(
        $uri = '/',
        $method = 'get'
    ) {
        return new ServerRequest(
            $this->buildSwooleRequest($uri, $method),
            $this->uriFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );
    }
}
