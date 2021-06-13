<?php
use Imefisto\PsrSwoole\Request as PsrRequest;
use Imefisto\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request;
use Swoole\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$http = new swoole_http_server("0.0.0.0", 9501);
$uriFactory = new Psr17Factory;
$streamFactory = new Psr17Factory;
$responseFactory = new Psr17Factory;
$responseMerger = new ResponseMerger;

$http->on(
    'request',
    function (
        Request $swooleRequest,
        Response $swooleResponse
    ) use (
        $uriFactory,
        $streamFactory,
        $responseFactory,
        $responseMerger
    ) {
        /**
         * create psr request from swoole request
         */
        $psrRequest = new PsrRequest(
            $swooleRequest,
            $uriFactory,
            $streamFactory
        );

        /**
         * process request (here you call your app or framework)
         */
        $psrResponse = $responseFactory->createResponse(401);
        $psrResponse->getBody()->write("Hello world!");

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
