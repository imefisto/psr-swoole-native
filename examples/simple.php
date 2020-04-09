<?php
require __DIR__ . '/../vendor/autoload.php';

use Inek\PsrSwoole\Request as PsrRequest;
use Inek\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request;
use Swoole\Http\Response;

$http = new swoole_http_server("0.0.0.0", 9501);
$uriFactory = new Psr17Factory;
$streamFactory = new Psr17Factory;
$responseFactory = new Psr17Factory;
$responseMerger = new ResponseMerger;

$http->on(
    'request',
    function (Request $swooleRequest, Response $swooleResponse) use ($uriFactory, $streamFactory, $responseFactory, $responseMerger) {
        /**
         * create psr request from swoole request
         */
        $psrRequest = new PsrRequest(
            $swooleRequest,
            $uriFactory,
            $streamFactory
        );

        /**
         * process request (here you call your app or framework
         */
        $psrResponse = processRequest(
            $psrRequest,
            $responseFactory,
            $streamFactory
        );

        /**
         * merge your psr response with swoole response
         */
        $responseMerger->toSwoole(
            $psrResponse,
            $swooleResponse
        )->end();
    }
);

$http->start();

function processRequest($psrRequest, $responseFactory, $streamFactory)
{
    $body = json_encode([
        'status' => 'success',
    ]);

    return $responseFactory->createResponse()
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->withBody($streamFactory->createStream($body));
}
