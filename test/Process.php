<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = new React\EventLoop\StreamSelectLoop();

// setup information stream
$info = new React\Stream\Stream(fopen('php://stderr', 'w'), $loop);
$info->pause();

// setup input and output streams and pipe inbetween
$in = new React\Stream\Stream(fopen('php://stdin', 'r'), $loop);
$out = new React\Stream\Stream(fopen('php://stdout', 'w'), $loop);
$out->pause();

$input = '';
$exitCode = 0;
$in->on(
    'data',
    function ($data) use (&$input) {
        $input .= $data;
    }
);
$in->on(
    'close',
    function () use (&$input, &$exitCode, $out, $info, $loop, $in) {
        $meta = [];
        if (false === ($result = preg_match('/^\|(\d\d)\|(\d\d\d\d)\|/', $input, $meta)) || $result == 0) {
            $in->pause();
            $info->end('[Process] Got Bad Input "' . substr($input, 0, 32) . '".' . PHP_EOL);
            $exitCode = 1;
            return;
        }
        $info->write('[Process] Got Request from Client ' . $meta[1] . ' [mid: ' . $meta[2] . '].' .PHP_EOL);
        $rBody = sprintf(
            '|ACK|%s|%s|%d|',
            $meta[1],
            $meta[2],
            strlen($input)
        );
        $out->end($rBody);
    }
);

$loop->run();

exit($exitCode);
