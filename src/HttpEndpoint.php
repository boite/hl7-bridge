<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Request;
use React\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server($loop);

$server = new \React\Http\Server($socket);
$server->on('request', function (Request $request, Response $response) {
    echo("[HTTP] got request.\n");
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

$socket->listen(8910, '127.0.0.1');

echo 'Listening on ' . $socket->getPort() . PHP_EOL;

$loop->run();
