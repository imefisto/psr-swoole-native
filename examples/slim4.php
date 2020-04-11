<?php
use Imefisto\PsrSwoole\ServerRequest as PsrRequest;
use Imefisto\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Factory\AppFactory;
use Swoole\Http\Request;
use Swoole\Http\Response;

require __DIR__ . '/vendor/autoload.php';

/**
 * Create your slim app
 */
$app = AppFactory::create();

/**
 * Define your routes
 */
$app->get('/', function ($request, $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$http = new swoole_http_server("0.0.0.0", 9501);
$uriFactory = new Psr17Factory;
$streamFactory = new Psr17Factory;
$responseFactory = new Psr17Factory;
$uploadedFileFactory = new Psr17Factory;
$responseMerger = new ResponseMerger;

$http->on(
    'request',
    function (
        Request $swooleRequest,
        Response $swooleResponse
    ) use (
        $uriFactory,
        $streamFactory,
        $uploadedFileFactory,
        $responseFactory,
        $responseMerger,
        $app
    ) {
        /**
         * create psr request from swoole request
         */
        $psrRequest = new PsrRequest(
            $swooleRequest,
            $uriFactory,
            $streamFactory,
            $uploadedFileFactory
        );

        /**
         * process request (here is where slim handles the request)
         */
        $psrResponse = $app->handle($psrRequest);

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
