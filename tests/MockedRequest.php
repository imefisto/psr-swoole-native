<?php
namespace Imefisto\PsrSwoole\Testing;

use Swoole\Http\Request;

class MockedRequest extends Request
{
    public ?string $rawBody = null;

    public function rawContent(): string|false
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        if (empty($this->post)) {
            return false;
        }

        return http_build_query($this->post);
    }
}
