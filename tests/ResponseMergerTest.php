<?php
namespace Imefisto\PsrSwoole\Testing;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Response;
use Psr\Http\Message\ResponseInterface;

use Imefisto\PsrSwoole\ResponseMerger;

/**
 * @covers Imefisto\PsrSwoole\ResponseMerger
 */
class ResponseMergerTest extends TestCase
{
    protected $responseMerger;
    protected $swooleResponse;
    protected $body;
    protected $psrResponse;

    public function setUp(): void
    {
        parent::setUp();
        $this->responseMerger = new ResponseMerger();

        $this->swooleResponse = new MockedResponse;
        $this->body = $this->getMockForAbstractClass(\Psr\Http\Message\StreamInterface::class);

        $this->body->method('getMetadata')
                   ->will($this->returnValueMap([
                       [ 'wrapper_type', 'PHP' ],
                       [ 'stream_type', 'TEMP' ],
                   ]));
        
        $this->psrResponse = $this->getMockBuilder(ResponseInterface::class)->getMockForAbstractClass();
        $this->psrResponse->expects($this->any())->method('getBody')->willReturn($this->body);
    }

    /**
     * @test
     */
    public function returnsSwooleResponse()
    {
        $swooleResponse = $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);
        $this->assertInstanceOf(Response::class, $swooleResponse);
    }

    /**
     * @test
     */
    public function headersGetCopied()
    {
        $this->psrResponse->expects($this->any())->method('getHeaders')->willReturn([
            'foo' => ['bar'],
            'fiz' => ['bam']
        ]);
        $this->psrResponse->method('withoutHeader')->willReturn($this->psrResponse);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);
        $this->assertEquals(
            2,
            $this->swooleResponse->countCalls('header')
        );
    }

    /**
     * @test
     */
    public function cookiesShouldBeMergedWithCookieMethod()
    {
        $psrResponseWithoutCookies = clone $this->psrResponse;
        $psrResponseWithoutCookies->method('getHeaders')->willReturn([]);
        $this->psrResponse->method('withoutHeader')->willReturn($psrResponseWithoutCookies);
        $expires = new \Datetime('+2 hours');
        
        $cookieArray = [
            'Cookie1=Value1; Domain=some-domain; Path=/; Expires='
            . $expires->format(\DateTime::COOKIE) . ' GMT; Secure; HttpOnly',
        ];

        $this->psrResponse->expects($this->any())->method('getHeaders')->willReturn([
            'Set-Cookie' => $cookieArray
        ]);
        $this->psrResponse->method('getHeader')->willReturn($cookieArray);
        $this->psrResponse->method('hasHeader')->willReturn(true);

        // $this->swooleResponse->expects($headerSpy = $this->exactly(0))->method('header');
        // $this->swooleResponse->expects($cookieSpy = $this->exactly(1))->method('cookie')
        //     ->with('Cookie1', 'Value1', $expires->getTimestamp(), '/', 'some-domain', true, true);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertEquals(0, $this->swooleResponse->countCalls('header'));
        $this->assertEquals(1, $this->swooleResponse->countCalls('cookie'));
        $this->assertEquals(
            [
                'Cookie1',
                'Value1',
                $expires->getTimestamp(),
                '/',
                'some-domain',
                true,
                true,
                null
            ],
            $this->swooleResponse->call('cookie')
        );
    }

    /**
     * @test
     */
    public function cookiesSupportSameSiteAttribute()
    {
        $expires = new \Datetime('+2 hours');

        $cookieArray = [
            'Cookie1=Value1; Domain=some-domain; Path=/; Expires='
            . $expires->format(\DateTime::COOKIE) . ' GMT; Secure; HttpOnly; SameSite=None',
            'Cookie2=Value2; Domain=some-domain; Path=/; Expires='
            . $expires->format(\DateTime::COOKIE) . ' GMT; Secure; HttpOnly; SameSite=Lax',
            'Cookie3=Value3; Domain=some-domain; Path=/; Expires='
            . $expires->format(\DateTime::COOKIE) . ' GMT; Secure; HttpOnly; SameSite=Strict',
        ];

        $this->psrResponse->method('getHeaders')->willReturn([
            'Set-Cookie' => $cookieArray
        ]);
        $this->psrResponse->method('getHeader')->willReturn($cookieArray);
        $this->psrResponse->method('hasHeader')->willReturn(true);
        $this->psrResponse->method('withoutHeader')->willReturn($this->psrResponse);

        $expectedCalls = [
            ['Cookie1', 'Value1', $expires->getTimestamp(), '/', 'some-domain', true, true, 'none'],
            ['Cookie2', 'Value2', $expires->getTimestamp(), '/', 'some-domain', true, true, 'lax'],
            ['Cookie3', 'Value3', $expires->getTimestamp(), '/', 'some-domain', true, true, 'strict'],
        ];

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertEquals(3, $this->swooleResponse->countCalls('cookie'));
        foreach ($expectedCalls as $at => $call) {
            $this->assertEquals($call, $this->swooleResponse->call('cookie', $at));
        }
    }

    /**
     * @test
     */
    public function bodyContentGetsCopiedIfNotEmpty()
    {
        $this->body->expects($this->once())->method('getSize')->willReturn(1);
        $this->body->expects($this->once())->method('isSeekable')->willReturn(true);
        $this->body->expects($rewindSpy = $this->once())->method('rewind')->willReturn(null);
        $this->body->expects($this->once())->method('rewind')->willReturn(null);
        $this->body->expects($this->once())->method('getContents')->willReturn('abc');

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertEquals(1, $this->swooleResponse->countCalls('write'));
        $this->assertEquals(['abc'], $this->swooleResponse->call('write'));

        $this->assertSame(1, $rewindSpy->getInvocationCount());
    }

    /**
     * @test
     */
    public function bodyContentGetsWrittenIfItIsAPipe()
    {
        $this->body->method('getMetadata')
                   ->will($this->returnValueMap([
                       ['mode', 0010600],
                   ]));

        $expectedProcess = popen('php -r "echo str_repeat(\'x\', 16384);"', 'r');
        
        $this->body->expects($this->any())
                   ->method('detach')->willReturn($expectedProcess);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);
        $this->assertEquals('Unknown', get_resource_type($expectedProcess));
        $this->assertEquals(
            2,
            $this->swooleResponse->countCalls('write')
        );

        for ($i = 0; $i < $this->swooleResponse->countCalls('write'); $i++) {
            $contents = $this->swooleResponse->call('write', $i);
            $this->assertEquals(ResponseMerger::BUFFER_SIZE, strlen($contents[0]));
        }
    }

    /**
     * @test
     */
    public function sendsFileIfThereIsFileStreamInBody()
    {
        $factory = new Psr17Factory;
        $expectedUri = __DIR__ . '/dummy.pdf';
        $stream = $factory->createStreamFromFile($expectedUri);
        $psrResponse = $factory->createResponse()
            ->withBody($stream);

        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);

        $this->assertEquals(1, $this->swooleResponse->countCalls('sendfile'));
        $this->assertEquals([$expectedUri], $this->swooleResponse->call('sendfile'));
    }

    /**
     * @test
     */
    public function statusCodeGetsCopied()
    {
        $this->psrResponse->expects($this->once())->method('getStatusCode')->willReturn(400);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertEquals(1, $this->swooleResponse->countCalls('status'));
        $this->assertEquals(400, $this->swooleResponse->call('status')[0]);
    }
}
