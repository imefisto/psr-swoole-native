<?php
namespace Imefisto\PsrSwoole\Testing;

use Swoole\Http\Request;

class MockedRequest extends Request
{
    public function rawContent()
    {
        if (empty($this->post)) {
            return false;
        }

        return http_build_query($this->post);
    }
}
