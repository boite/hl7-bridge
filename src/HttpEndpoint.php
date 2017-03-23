<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Request;
use React\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server('127.0.0.1:8910', $loop);

$server = new \React\Http\Server($socket);
$server->on('request', function (Request $request, Response $response) {
    echo '[HTTP] got request.' . PHP_EOL;
    $response
        ->writeHead(
            200,
            array(
                'Content-Type' => 'x-application/hl7-v2+er7',
                'Content-Length' => 6,
            )
        )
    ;
    $response->end("|ACK|\r");
});

echo '[HTTP] Listening on 127.0.0.1:8910' . PHP_EOL;

$loop->run();
