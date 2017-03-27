<?php

namespace LinkORB\HL7\Transport\Http;

use React\HttpClient\Client;
use React\HttpClient\Response;
use React\Socket\ConnectionInterface;

use LinkORB\HL7\Transport\TransportForwardInterface;

/**
 * Forward messages to an HTTP endpoint.
 */
class HttpTransport implements TransportForwardInterface
{
    /**
     * @var \React\HttpClient\Client
     */
    private $client;

    /**
     * @var \LinkORB\HL7\Transport\Http\HttpResponseHandlerFactory
     */
    private $httpResponseHandlerFactory;

    private $url;

    /**
     * @param string $url URL of the HTTP endpoint.
     * @param Client $client An HTTP client.
     * @param HttpResponseHandlerFactory $httpResponseHandlerFactory
     */
    public function __construct(
        $url,
        Client $client,
        HttpResponseHandlerFactory $httpResponseHandlerFactory
    ) {
            $this->url = $url;
            $this->client = $client;
            $this->httpResponseHandlerFactory = $httpResponseHandlerFactory;
    }

    /**
     * Forward messages to an HTTP endpoint and set-up a handler for each response.
     *
     * @param ConnectionInterface $conn
     * @param string $message
     */
    public function forward(ConnectionInterface $conn, $message)
    {
        $request = $this->client->request(
            'POST',
            $this->url,
            [
                'Content-Type' => 'x-application/hl7-v2+er7',
                'Content-Length' => strlen($message)
            ]
        );

        $request->on(
            'response',
            function (Response $response) use ($conn) {
                if (200 != $response->getCode()) {
                    return;
                }
                $responseHandler = $this->httpResponseHandlerFactory->create();
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
