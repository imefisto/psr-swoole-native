<?php
namespace Imefisto\PsrSwoole\Testing;

use Swoole\Http\Response;

class LegacyMockedResponse extends BaseMockedResponse
{
    public function cookie(
        $name,
        $value = '',
        $expires = 0,
        $path = '/',
        $domain = '',
        $secure = false,
        $httponly = false,
        $samesite = '',
        $priority = ''
    ) {
        $this->registerCall('cookie', func_get_args());
        return true;
    }
}
