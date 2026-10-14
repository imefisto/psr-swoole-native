<?php
namespace Imefisto\PsrSwoole\Testing;

use Imefisto\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * @covers Imefisto\PsrSwoole\ResponseMerger
 */
class ResponseMergerHeadersTest extends TestCase
{
    private ResponseMerger $responseMerger;
    private BaseMockedResponse $swooleResponse;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->responseMerger = new ResponseMerger();
        $this->swooleResponse = MockedResponseFactory::create();
        $this->factory = new Psr17Factory();
    }

    /**
     * @test
     */
    public function headerWithManyValuesIsJoinedWithComma()
    {
        $psrResponse = $this->factory->createResponse()
            ->withHeader('Vary', ['Accept', 'Accept-Encoding']);

        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);

        $this->assertEquals(['Vary', 'Accept, Accept-Encoding'], $this->swooleResponse->call('header'));
    }

    /**
     * @test
     */
    public function cookieMaxAgeIsConvertedToExpires()
    {
        $psrResponse = $this->factory->createResponse()
            ->withHeader('Set-Cookie', 'session=abc; Path=/; Max-Age=3600');

        $before = time();
        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);
        $after = time();

        $expires = $this->swooleResponse->call('cookie')[2];
        $this->assertGreaterThanOrEqual($before + 3600, $expires);
        $this->assertLessThanOrEqual($after + 3600, $expires);
    }

    /**
     * @test
     */
    public function cookieMaxAgeTakesPrecedenceOverExpires()
    {
        $psrResponse = $this->factory->createResponse()
            ->withHeader('Set-Cookie', 'session=abc; Expires=Wed, 21 Oct 2037 07:28:00 GMT; Max-Age=60');

        $before = time();
        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);
        $after = time();

        $expires = $this->swooleResponse->call('cookie')[2];
        $this->assertGreaterThanOrEqual($before + 60, $expires);
        $this->assertLessThanOrEqual($after + 60, $expires);
    }

    /**
     * @test
     */
    public function cookieWithZeroMaxAgeExpiresInThePast()
    {
        $psrResponse = $this->factory->createResponse()
            ->withHeader('Set-Cookie', 'session=; Path=/; Max-Age=0');

        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);

        // 0 would mean "session cookie" for Swoole, so the cookie would not be removed
        $this->assertSame(1, $this->swooleResponse->call('cookie')[2]);
    }
}
