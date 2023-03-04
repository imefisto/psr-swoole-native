<?php
namespace Imefisto\PsrSwoole\Testing;

use Swoole\Http\Response;

class MockedResponse extends Response
{
    private $calls = [];

    public function header($key, $value, $format = true): bool
    {
        $this->registerCall('header', func_get_args());
        return true;
    }

    public function status($http_code, $reason = ''): bool
    {
        $this->registerCall('status', func_get_args());
        return true;
    }

    public function write($body): bool
    {
        $this->registerCall('write', func_get_args());
        return true;
    }

    public function cookie(
        $key,
        $value = '',
        $expire = 0,
        $path = '/',
        $domain = '',
        $secure = false,
        $httponly = false,
        $samesite = '',
        $priority = ''
    ):bool {
        $this->registerCall('cookie', func_get_args());
        return true;
    }

    public function sendfile($filename, $offset = 0, $length = 0): bool
    {
        $this->registerCall('sendfile', func_get_args());
        return true;
    }
    
    private function registerCall($method, $args)
    {
        if (!isset($this->calls[$method])) {
            $this->calls[$method] = [];
        }

        $this->calls[$method][] = $args;
    }

    public function countCalls($method): int
    {
        return isset($this->calls[$method])
            ? count($this->calls[$method])
            : 0
            ;
    }

    public function call($method, $at = 0)
    {
        return $this->calls[$method][$at];
    }
}
