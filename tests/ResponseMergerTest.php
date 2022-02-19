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
    public function setUp(): void
    {
        parent::setUp();
        $this->responseMerger = new ResponseMerger();

        $this->swooleResponse = $this->getMockBuilder(Response::class)->disableOriginalConstructor()->getMock();
        $this->body = $this->getMockForAbstractClass(\Psr\Http\Message\StreamInterface::class);
        
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

        $this->swooleResponse->expects($headerSpy = $this->exactly(2))->method('header');

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);
        $this->assertSame(2, $headerSpy->getInvocationCount());
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
        $this->swooleResponse->expects($headerSpy = $this->exactly(0))->method('header');
        $this->swooleResponse->expects($cookieSpy = $this->exactly(1))->method('cookie')
            ->with('Cookie1', 'Value1', $expires->getTimestamp(), '/', 'some-domain', true, true);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertSame(0, $headerSpy->getInvocationCount());
        $this->assertSame(1, $cookieSpy->getInvocationCount());
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

        $this->swooleResponse->expects($cookieSpy = $this->exactly(3))
                             ->method('cookie')
                             ->withConsecutive(
                                 ['Cookie1', 'Value1', $expires->getTimestamp(), '/', 'some-domain', true, true, 'none'],
                                 ['Cookie2', 'Value2', $expires->getTimestamp(), '/', 'some-domain', true, true, 'lax'],
                                 ['Cookie3', 'Value3', $expires->getTimestamp(), '/', 'some-domain', true, true, 'strict'],
                             );

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);
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
        $this->swooleResponse->expects($writeSpy = $this->once())->method('write')->with('abc');

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertSame(1, $rewindSpy->getInvocationCount());
        $this->assertSame(1, $writeSpy->getInvocationCount());
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

        $this->swooleResponse->expects($writeSpy = $this->atLeastOnce())
                             ->method('write')
                             ->with($this->callback(function ($contents) {
                                 return strlen($contents) == ResponseMerger::BUFFER_SIZE;
                             }));

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);
        $this->assertEquals('Unknown', get_resource_type($expectedProcess));
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

        $this->swooleResponse->expects($this->once())
                             ->method('sendfile')
                             ->with($expectedUri);

        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);
    }

    /**
     * @test
     */
    public function statusCodeGetsCopied()
    {
        $this->psrResponse->expects($this->once())->method('getStatusCode')->willReturn(400);
        $this->swooleResponse->expects($setStatusSpy = $this->once())->method('status')->with(400);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertSame(1, $setStatusSpy->getInvocationCount());
    }
}
