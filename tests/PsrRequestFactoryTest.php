<?php
namespace Imefisto\PsrSwoole\Testing;

use PHPUnit\Framework\TestCase;

use Imefisto\PsrSwoole\PsrRequestFactory;
use Imefisto\PsrSwoole\ServerRequest;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class PsrRequestFactoryTest extends TestCase
{
    use SwooleRequestBuilderTrait;

    protected PsrRequestFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PsrRequestFactory(
            $this->createMock(UriFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(UploadedFileFactoryInterface::class)
        );
    }

    public function testCreateServerRequest()
    {
        $swooleRequest = $this->buildSwooleRequest('/', 'get');
        $psrRequest = $this->factory->createServerRequest($swooleRequest);
        $this->assertInstanceOf(ServerRequest::class, $psrRequest);
        $this->assertSame($swooleRequest, $psrRequest->swooleRequest);
    }
}
