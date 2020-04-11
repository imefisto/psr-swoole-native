<?php
namespace Imefisto\PsrSwoole\Testing;

use Swoole\Http\Request as SwooleRequest;

trait SwooleRequestBuilderTrait
{
    private function buildSwooleRequest(
        $uri,
        $method = 'get',
        $postBody = null
    ) {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->getMock();
        $swooleRequest->server = [
            'request_method' => $method,
            'request_uri' => $uri,
            'server_protocol' => 'HTTP/1.1',
        ];
        $swooleRequest->header = [
            'host' => 'localhost:9501'
        ];
        $swooleRequest->post = $postBody;

        $swooleRequest
             ->expects($this->any())
             ->method('rawContent')
             ->willReturn($this->mockRawContent($swooleRequest));

        return $swooleRequest;
    }

    private function mockRawContent($swooleRequest)
    {
        if (empty($swooleRequest->post)) {
            return null;
        }

        return http_build_query($swooleRequest->post);
    }
}
