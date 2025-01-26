<?php
namespace Imefisto\PsrSwoole\Testing;

class MockedResponseFactory
{
    public static function create(): BaseMockedResponse
    {
        if (version_compare(SWOOLE_VERSION, '5.0.0', '>=')) {
            return new MockedResponse();
        }
        return new LegacyMockedResponse();
    }
}
