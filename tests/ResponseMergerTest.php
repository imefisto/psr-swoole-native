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
    protected $psrResponse;

    public function setUp(): void
    {
        $this->responseMerger = new ResponseMerger();
        $this->swooleResponse = MockedResponseFactory::create();
        $this->psrResponse = $this->getMockBuilder(ResponseInterface::class)->getMockForAbstractClass();
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
                'lax'
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
    public function returnsSameSiteLaxIfNotSpecified()
    {
        $expires = new \Datetime('+2 hours');

        $cookieArray = [
            'Cookie1=Value1; Domain=some-domain; Path=/; Expires='
            . $expires->format(\DateTime::COOKIE) . ' GMT',
        ];

        $this->psrResponse->method('getHeaders')->willReturn([
            'Set-Cookie' => $cookieArray
        ]);
        $this->psrResponse->method('getHeader')->willReturn($cookieArray);
        $this->psrResponse->method('hasHeader')->willReturn(true);
        $this->psrResponse->method('withoutHeader')->willReturn($this->psrResponse);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertEquals(1, $this->swooleResponse->countCalls('cookie'));

        $expectedCall = ['Cookie1', 'Value1', $expires->getTimestamp(), '/', 'some-domain', false, false, 'lax'];

        $this->assertEquals($expectedCall, $this->swooleResponse->call('cookie', 0));
    }

    /**
     * @test
     */
    public function callsSetCookieWithProperDefaultValues()
    {
        $expires = new \Datetime('+2 hours');

        $cookieArrayWithoutDomain = [ 'Cookie1=Value1' ];

        $this->psrResponse->method('getHeaders')->willReturn([
            'Set-Cookie' => $cookieArrayWithoutDomain
        ]);
        $this->psrResponse->method('getHeader')->willReturn($cookieArrayWithoutDomain);
        $this->psrResponse->method('hasHeader')->willReturn(true);
        $this->psrResponse->method('withoutHeader')->willReturn($this->psrResponse);

        $this->responseMerger->toSwoole($this->psrResponse, $this->swooleResponse);

        $this->assertEquals(
            [
                'Cookie1',
                'Value1',
                0,
                '/',
                '',
                false,
                false,
                'lax'
            ],
            $this->swooleResponse->call('cookie')
        );
    }

    /**
     * @test
     */
    public function bodyContentGetsCopiedIfNotEmpty()
    {
        $resource = fopen('php://memory', 'r+');
        $content = 'test content';
        fwrite($resource, $content);
        rewind($resource);
        $factory = new Psr17Factory;
        $stream = $factory->createStreamFromResource($resource);
        $psrResponse = $factory->createResponse()->withBody($stream);

        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);

        $this->assertEquals(1, $this->swooleResponse->countCalls('end'));
        $this->assertEquals($content, $this->swooleResponse->call('end')[0]);
    }

    /**
     * @test
     */
    public function bodyContentGetsCopiedFromInitialPosition()
    {
        $resource = fopen('php://memory', 'r+');
        $content = 'test content';
        fwrite($resource, $content);
        fseek($resource, 5);
        $factory = new Psr17Factory;
        $stream = $factory->createStreamFromResource($resource);
        $psrResponse = $factory->createResponse()->withBody($stream);
        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);
        $this->assertEquals($content, $this->swooleResponse->call('end')[0]);
    }

    /**
     * @test
     */
    public function bodyCanGetCopiedUsingChunks()
    {
        $resource = fopen('php://memory', 'r+');
        $content = str_repeat('x', 2048);
        fwrite($resource, $content);
        $factory = new Psr17Factory;
        $stream = $factory->createStreamFromResource($resource);
        $psrResponse = $factory->createResponse()->withBody($stream);
        $autoChunkSize = 1024;
        $responseMerger = new ResponseMerger(autoChunkSize: 1024);

        $responseMerger->toSwoole($psrResponse, $this->swooleResponse);

        $this->assertEquals(
            2, 
            $this->swooleResponse->countCalls('write')
        );

        $this->assertEquals(
            substr($content, 0, $autoChunkSize),
            $this->swooleResponse->call('write', 0)[0]
        );

        $this->assertEquals(
            substr($content, $autoChunkSize),
            $this->swooleResponse->call('write', 1)[0]
        );

        $this->assertEquals(
            1, 
            $this->swooleResponse->countCalls('end')
        );

        $this->assertEquals(
            [],
            $this->swooleResponse->call('end')
        );
    }

    /**
     * @test
     */
    public function bodyContentGetsWrittenIfItIsAPipe()
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
        ];

        $process = proc_open('cat', $descriptorSpec, $pipes);
        fwrite($pipes[0], str_repeat('x', 16384));
        fclose($pipes[0]);

        $factory = new Psr17Factory;

        $stream = $factory->createStreamFromResource($pipes[1]);
        $psrResponse = $factory->createResponse()->withBody($stream);

        $this->responseMerger->toSwoole($psrResponse, $this->swooleResponse);

        $numberOfWrites = $this->swooleResponse->countCalls('write');
        $this->assertEquals(2, $numberOfWrites);

        for ($i = 0; $i < $numberOfWrites; $i++) {
            $contents = $this->swooleResponse->call('write', $i);
            $this->assertEquals(
                ResponseMerger::BUFFER_SIZE,
                strlen((string) $contents[0])
            );
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
