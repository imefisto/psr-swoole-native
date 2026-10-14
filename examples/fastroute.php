<?php
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Imefisto\PsrSwoole\PsrRequestFactory;
use Imefisto\PsrSwoole\ResponseMerger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

use function FastRoute\simpleDispatcher;

// Requires: composer require nikic/fast-route
require __DIR__ . '/../vendor/autoload.php';

$psr17Factory = new Psr17Factory;
$requestFactory = new PsrRequestFactory($psr17Factory, $psr17Factory, $psr17Factory);
$responseMerger = new ResponseMerger;

/**
 * Handlers receive the PSR-7 request and the route variables,
 * and return a PSR-7 response
 */
$json = function (mixed $data, int $status = 200) use ($psr17Factory): ResponseInterface {
    $response = $psr17Factory->createResponse($status)
        ->withHeader('Content-Type', 'application/json');
    $response->getBody()->write(json_encode($data));
    return $response;
};

$dispatcher = simpleDispatcher(function (RouteCollector $r) use ($json) {
    $r->get('/hello/{name}', function (ServerRequestInterface $request, array $vars) use ($json) {
        return $json(['hello' => $vars['name']]);
    });

    // try: curl -X POST -H 'Content-Type: application/json' localhost:9501/echo --data '{"a":1}'
    $r->post('/echo', function (ServerRequestInterface $request, array $vars) use ($json) {
        return $json(['received' => $request->getParsedBody()]);
    });
});

$http = new Server("0.0.0.0", 9501);

$http->on(
    'request',
    function (
        Request $swooleRequest,
        Response $swooleResponse
    ) use (
        $requestFactory,
        $responseMerger,
        $dispatcher,
        $json
    ) {
        /**
         * create psr request from swoole request
         */
        $psrRequest = $requestFactory->createServerRequest($swooleRequest);

        /**
         * route the request
         */
        $route = $dispatcher->dispatch($psrRequest->getMethod(), $psrRequest->getUri()->getPath());

        $psrResponse = match ($route[0]) {
            Dispatcher::FOUND => $route[1]($psrRequest, $route[2]),
            Dispatcher::METHOD_NOT_ALLOWED => $json(['error' => 'Method not allowed'], 405)
                ->withHeader('Allow', implode(', ', $route[1])),
            default => $json(['error' => 'Not found'], 404),
        };

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
