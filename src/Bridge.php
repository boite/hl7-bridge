<?php

use React\Socket\ConnectionInterface;
use React\HttpClient\Response;
use React\HttpClient\Factory as HttpClientFactory;
use React\Socket\Server;
use React\Dns\Resolver\Factory as DnsResolverFactory;

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new DnsResolverFactory();
$dnsResolver = $dnsResolverFactory->createCached('192.168.1.10', $loop);

$clientFactory = new HttpClientFactory();
$client = $clientFactory->create($loop, $dnsResolver);

$socket = new Server($loop);
$socket
    ->on(
        'connection',
        function (ConnectionInterface $conn) use ($client) {
            $mllp_buffer = '';
            $conn
                ->on(
                    'data',
                    function($data) use (&$mllp_buffer) {
                        printf("[MLLP] Received %d bytes into buffer\n", strlen($data));
                        $mllp_buffer .= $data;
                    }
                )
            ;
            $conn
                ->on(
                    'end',
                    function() use (&$mllp_buffer, $client, $conn) {
                        # POST the content of the MLLP buffer
                        printf("[MLLP] Received %d bytes.\n", strlen($mllp_buffer));
                        $request = $client
                            ->request(
                                'POST',
                                'http://127.0.0.1:8910/',
                                array(
                                    'Content-Type' => 'x-application/hl7-v2+er7',
                                    'Content-Length' => strlen($mllp_buffer)
                                )
                            )
                        ;
                        $request
                            ->on(
                                'response',
                                function (Response $response) use ($conn) {
                                    echo("[MLLP] Got response to POST\n");
                                    # Write response data into the MLLP connection.
                                    $response
                                        ->on(
                                            'data',
                                            function ($data) use ($conn) {
                                                printf("[MLLP] Writing %d bytes back to sender.\n", strlen($data));
                                                $conn->write($data);
                                            }
                                        )
                                    ;
                                    # End of the response. Reply to MLLP sender.
                                    $response
                                        ->on(
                                            'end',
                                            function () use ($conn) {
                                                echo("[MLLP] Ending connection from sender.\n");
                                                $conn->end();
                                                $conn->close();
                                            }
                                        )
                                    ;
                                }
                            )
                        ;
                        printf("[MLLP] POSTing %d bytes\n", strlen($mllp_buffer));
                        $request->end($mllp_buffer);
                    }
                )
            ;
        }
    )
;

echo "Socket server listening on port 4000.\n";
echo "You can connect to it by running: telnet localhost 4000\n";
$socket->listen(4000);
$loop->run();