<?php
namespace Inek\PsrSwoole\Testing;

use Swoole\Http\Request as SwooleRequest;

trait SwooleRequestBuilderTrait
{
    private function buildSwooleRequest($uri, $method = 'get', $queryString = '', $userInfo = null, $headers = [], $post = null, $cookies = [])
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
        $swooleRequest->post = $post;

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

        if (!empty($cookies)) {
            $swooleRequest->cookie = $cookies;
        }

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
