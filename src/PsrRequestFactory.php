<?php
namespace Imefisto\PsrSwoole;

use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Swoole\Http\Request as SwooleRequest;

class PsrRequestFactory
{
    public function __construct(
        private readonly UriFactoryInterface $uriFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly UploadedFileFactoryInterface $uploadedFileFactory
    ) {
    }

    public function createServerRequest(
        SwooleRequest $swooleRequest
    ): ServerRequest {
        return new ServerRequest(
            $swooleRequest,
            $this->uriFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );
    }
}
