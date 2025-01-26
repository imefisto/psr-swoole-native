<?php
use Imefisto\PsrSwoole\PsrRequestFactory;
use Imefisto\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$http = new Server("0.0.0.0", 9501);
$uriFactory = new Psr17Factory;
$streamFactory = new Psr17Factory;
$uploadedFileFactory = new Psr17Factory;

$requestFactory = new PsrRequestFactory($uriFactory, $streamFactory, $uploadedFileFactory);
$responseFactory = new Psr17Factory;
$responseMerger = new ResponseMerger;

$http->on(
    'request',
    function (
        Request $swooleRequest,
        Response $swooleResponse
    ) use (
        $requestFactory,
        $responseFactory,
        $responseMerger
    ) {
        /**
         * create psr request from swoole request
         */
        $psrRequest = $requestFactory->createServerRequest($swooleRequest);

        /**
         * process request (here you call your app or framework)
         */
        $psrResponse = $responseFactory->createResponse();
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
