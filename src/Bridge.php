<?php

use React\Socket\ConnectionInterface;
use React\HttpClient\Response;
use React\HttpClient\Factory as HttpClientFactory;
use React\Socket\Server;
use React\Dns\Resolver\Factory as DnsResolverFactory;
use React\HttpClient\Client;

require __DIR__.'/../vendor/autoload.php';

const CONF_LISTEN_HOST = '127.0.0.1';
const CONF_LISTEN_PORT = 4000;
const CONF_DNS_HOST = '192.168.1.10';
const CONF_HTTP_ENDPOINT_URL = 'http://127.0.0.1:8910/';

class MllpRequestHandler
{
    const HEADER = "\x0B";
    const TRAILER = "\x1C";
    const IN = 1;  // Inside a message
    const OUT = 0; // Not inside a message

    private $buffer = '';

    public function handleMllpData($data)
    {
        $this->buffer .= $data;
        return $this->processBuffer();
    }

    private function processBuffer()
    {
        $messages = [];

        $process_ptr = 0;
        $state = self::OUT;

        $rchars = array_reverse(str_split($this->buffer));
        $buflen = sizeof($rchars);
        $rmessage = [];

        for ($i = 0; $i < $buflen; $i++) {
            $c = array_pop($rchars);
            if ($state == self::IN && (self::HEADER != $c && self::TRAILER != $c)) {
                array_push($rmessage, $c);
            } elseif ($state == self::IN && self::TRAILER == $c) {
                $state = self::OUT;
                if (!empty($rmessage)) {
                    $messages[] = implode($rmessage);
                    $process_ptr = $i;
                    $rmessage = [];
                }
            } elseif ($state == self::OUT && self::HEADER == $c) {
                $state = self::IN;
            }
        }

        if ($process_ptr) {
            $this->buffer = substr($this->buffer, $process_ptr);
        }

        return $messages;
    }
}

class HttpTransporter
{
    private $httpResponseHandlerFactory;
    private $url;

    public function __construct(
        $url,
        Client $client,
        HttpResponseHandlerFactory $httpResponseHandlerFactory
    ) {
            $this->url = $url;
            $this->client = $client;
            $this->httpResponseHandlerFactory = $httpResponseHandlerFactory;
    }

    public function forward($conn, $message)
    {
        $request = $this->client->request(
            'POST',
            $this->url,
            [
                'Content-Type' => 'x-application/hl7-v2+er7',
                'Content-Length' => strlen($message)
            ]
        );
        $responseHandler = $this->httpResponseHandlerFactory->create();
        $request->on(
            'response',
            function (Response $response) use ($conn, $responseHandler) {
                $response->on(
                    'data',
                    function ($data) use ($responseHandler) {
                        $responseHandler->handleResponseData($data);
                    }
                );
                $response->on(
                    'end',
                    function () use ($conn, $responseHandler) {
                        $responseHandler->handleResponseCompletion($conn);
                    }
                );
            }
        );
        $request->end($message);
    }
}

class HttpResponseHandlerFactory
{
    private $mllpTransporter;

    public function __construct(MllpTransporter $mllpTransporter)
    {
        $this->mllpTransporter = $mllpTransporter;
    }

    public function create()
    {
        return new HttpResponseHandler($this->mllpTransporter);
    }
}

class HttpResponseHandler
{
    private $mllpTransport;
    private $buffer = '';

    public function __construct($mllpTransport)
    {
        $this->mllpTransport = $mllpTransport;
    }

    public function handleResponseData($data)
    {
        $this->buffer .= $data;
    }

    public function handleResponseCompletion($conn)
    {
        $this->mllpTransport->acknowledge($conn, $this->buffer);
    }
}

class MllpTransporter
{
    const HEADER = "\x0B";
    const TRAILER = "\x1C";

    public function acknowledge($conn, $message)
    {
        $conn->pause();
        $conn->write(sprintf("%s%s%s\r", self::HEADER, $message, self::TRAILER));
        $conn->resume();
    }
}

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new DnsResolverFactory();
$dnsResolver = $dnsResolverFactory->createCached(CONF_DNS_HOST, $loop);

$clientFactory = new HttpClientFactory();
$client = $clientFactory->create($loop, $dnsResolver);

$mmlpRequestHandler = new MllpRequestHandler();

$httpTransporter = new HttpTransporter(
    CONF_HTTP_ENDPOINT_URL,
    $client,
    new HttpResponseHandlerFactory(new MllpTransporter)
);

$socket = new Server(CONF_LISTEN_HOST . ':' . CONF_LISTEN_PORT, $loop);

$socket
    ->on(
        'connection',
        function (ConnectionInterface $conn) use ($mmlpRequestHandler, $httpTransporter) {
            $conn
                ->on(
                    'data',
                    function($data, $conn) use ($mmlpRequestHandler, $httpTransporter) {
                        $messages = $mmlpRequestHandler->handleMllpData($data);
                        foreach ($messages as $message) {
                            $httpTransporter->forward($conn, $message);
                        }
                    }
                )
            ;
            $conn
                ->on(
                    'end',
                    function() {
                        echo '[BRIDGE] Connection from client ends.' . PHP_EOL;
                    }
                )
            ;
        }
    )
;

echo '[BRIDGE] Listening on ' . CONF_LISTEN_HOST . ':' . CONF_LISTEN_PORT . PHP_EOL;

$loop->run();
