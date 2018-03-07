<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Request;
use React\Http\Response;
use React\Http\Server as HttpServer;
use React\Socket\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$httpServer = new HttpServer(function (ServerRequestInterface $request) {
    $body = (string) $request->getBody();
    $meta = [];
    if (false === ($result = preg_match('/^\|(\d\d)\|(\d\d\d\d)\|/', $body, $meta)) || $result == 0) {
        echo '[HTTP] Got Bad Request "' . substr($body, 0, 32) . '".' . PHP_EOL;
        return new Response(400, ['X-Powered-By' => []]);
    }
    echo '[HTTP] Got Request from Client ' . $meta[1] . ' [mid: ' . $meta[2] . '].' .PHP_EOL;
    $responseBody = sprintf(
        '|ACK|%s|%s|%d|',
        $meta[1],
        $meta[2],
        strlen($body)
    );
    return new Response(
        200,
        [
            'X-Powered-By' => [],
            'Content-Type' => 'x-application/hl7-v2+er7',
            'Content-Length' => strlen($responseBody),
        ],
        $responseBody
    );
});

$socket = new Server('127.0.0.1:8910', $loop);

$httpServer->listen($socket);

echo '[HTTP] Listening on 127.0.0.1:8910' . PHP_EOL;

$loop->run();
