<?php
namespace Imefisto\PsrSwoole\Testing;

use Swoole\Http\Response;

class MockedResponse extends BaseMockedResponse
{
    public function cookie(
        \Swoole\Http\Cookie|string $name_or_object,
        string $value = '',
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        string $samesite = '',
        string $priority = '',
        bool $partitioned = false
    ):bool {
        $this->registerCall('cookie', func_get_args());
        return true;
    }
}
