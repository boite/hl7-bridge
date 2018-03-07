<?php

use React\Socket\ConnectionInterface;

require __DIR__.'/../vendor/autoload.php';

const HEADER = "\x0B";
const TRAILER = "\x1C";
const URI = 'tcp://127.0.0.1:2575';


$clientID = '01';
if ($argc > 1 && (!is_numeric($argv[1]) || 0 > (int) $argv[1] || 99 < (int) $argv[1])) {
    echo 'Usage: ' . $argv[0] . ' [clientID]' . PHP_EOL;
    echo '(where 0 < clientID < 100)' . PHP_EOL;
    exit(1);
} elseif ($argc > 1) {
    $clientID = str_pad($argv[1], 2, '0', STR_PAD_LEFT);
}

$mid = 0;
$messages = []; // map of message ID to message length
$mllp = new LinkORB\HL7\Transport\Mllp\MllpRequestHandler();

$loop = React\EventLoop\Factory::create();

$connector = new React\Socket\Connector($loop);

$connector
    ->connect(URI)
    ->then(
        function (ConnectionInterface $stream) use ($loop, $clientID, &$mid, &$messages, $mllp) {
            $stream->on(
                'error',
                function ($e) {
                    throw $e;
                }
            );
            $stream->on(
                'data',
                function ($data) use ($clientID, $mllp, &$messages) {
                    foreach ($mllp->handleMllpData($data) as $m) {
                        echo $m . PHP_EOL;
                        $parts = explode('|', trim($m));
                        if (sizeof($parts) != 6) {
                            echo '[MLLP] Receive bogus response.' . PHP_EOL;
                            continue;
                        } elseif ($parts[1] != 'ACK') {
                            echo '[MLLP] Receive not an ACK.' . PHP_EOL;
                            continue;
                        } elseif ($parts[2] != $clientID) {
                            echo '[MLLP] Receive ACK for wrong client.' . PHP_EOL;
                            continue;
                        } elseif (!array_key_exists($parts[3], $messages)) {
                            echo '[MLLP] Receive ACK for unknown message ID ' . $parts[3] . '.' . PHP_EOL;
                            continue;
                        } elseif ($parts[4] != $messages[$parts[3]]) {
                            echo '[MLLP] Receive ACK. Message ID ' . $parts[3] . ' length should be ' . $messages[$parts[3]] . ', but got ' . $parts[4] . '.' . PHP_EOL;
                            continue;
                        }
                        unset($messages[$parts[3]]);
                        echo '[MLLP] Receive ' . trim($m) . PHP_EOL;
                    }
                }
            );
            $loop->addPeriodicTimer(
                1,
                function (React\EventLoop\Timer\Timer $timer) use ($loop, $stream, $clientID, &$mid, &$messages) {

                    if (sizeof($messages) > 60) {
                        echo '[MLLP] 60 Messages in flight. Stop sending.' . PHP_EOL;
                        $timer->cancel();
                        return;
                    }

                    $plen = 1 + (rand(1, 10) * 0xFFFF);
                    $p = str_repeat(
                        chr(ord('A') + (($clientID-1)%26)), // client 1: AAAA..., client 2: BBBB..., etc.
                        $plen
                    );
                    $pid = str_pad(++$mid, 4, '0', STR_PAD_LEFT);

                    $messages[$pid] = 1+ 2 +1+ 4 +1+ $plen +1;

                    echo sprintf('[MLLP] Send Message |%s|%s|... %s bytes.%s', $clientID, $pid, number_format((float) $messages[$pid]), PHP_EOL);
                    $stream->pause(); # stop any reads (for some reason, write does nothing when reads are going on) :/
                    $stream->write(sprintf("%s|%s|%s|%s|%s\r", HEADER, $clientID, $pid, $p, TRAILER));
                    $stream->resume(); # resume reads
                }
            );
            $loop->addPeriodicTimer(
                60,
                function () use (&$messages) {
                    echo '[MLLP] ' . sizeof($messages) . ' Messages in flight.' . PHP_EOL;
                }
            );
        }
    )
;

echo '[MLLP] Client (' . $clientID . ') is starting. Press Ctrl C to stop.' . PHP_EOL;

$loop->run();
