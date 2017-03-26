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
    $body = '';
    $request->on(
        'data',
        function ($data) use (&$body) {
            $body .= $data;
        }
    );
    $request->on(
        'end',
        function () use (&$body, $response) {
            $meta = [];
            if (false === ($result = preg_match('/^\|(\d\d)\|(\d\d\d\d)\|/', $body, $meta)) || $result == 0) {
                echo '[HTTP] Got Bad Request "' . substr($body, 0, 32) . '".' . PHP_EOL;
                $response->writeHead(400, ['X-Powered-By' => []]);
                $response->end();
                return;
            }
            echo '[HTTP] Got Request from Client ' . $meta[1] . ' [mid: ' . $meta[2] . '].' .PHP_EOL;
            $rBody = sprintf(
                '|ACK|%s|%s|%d|',
                $meta[1],
                $meta[2],
                strlen($body)
            );
            $response->writeHead(
                200,
                [
                    'X-Powered-By' => [],
                    'Content-Type' => 'x-application/hl7-v2+er7',
                    'Content-Length' => strlen($rBody),
                ]
            );
            $response->end($rBody);
        }
    );
});

echo '[HTTP] Listening on 127.0.0.1:8910' . PHP_EOL;

$loop->run();
