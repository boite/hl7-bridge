<?php

require __DIR__.'/../vendor/autoload.php';

const HEADER = "\x0B";
const TRAILER = "\x1C";
const CONF_SERVER_HOST = '127.0.0.1';
const CONF_SERVER_PORT = 2575;
const CONF_DNS_HOST = '127.0.0.1';

$messages = ['message-1', 'message-2', str_repeat('A', 1 + (7 * 0xFFFF))];

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached(CONF_DNS_HOST, $loop);

$connector = new React\SocketClient\Connector($loop, $dns);

$connector
    ->create(CONF_SERVER_HOST, CONF_SERVER_PORT)
    ->then(
        function (React\Stream\Stream $stream) use ($messages) {
            $stream->on('error', function ($e, $s) { throw $e; });
            $stream->on('data', function ($d, $s) { echo '[MLLP] Receive ' . trim($d) . PHP_EOL; });
            foreach ($messages as $message) {
                echo '[MLLP] Send Message.' . PHP_EOL;
                $stream->pause(); # stop any reads (for some reason, write does nothing when reads are going on) :/
                $stream->write(sprintf("%s%s%s\r", HEADER, $message, TRAILER));
                $stream->resume(); # resume reads
            }
        }
    )
;

echo '[MLLP] Client is starting. Press Ctrl C to stop.' . PHP_EOL;

$loop->run();
