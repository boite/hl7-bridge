<?php

namespace Linkorb\HL7\HTTP;

use React\HttpClient\Client;
use React\HttpClient\Response;
use React\Socket\ConnectionInterface;

/**
 * Forward messages to an HTTP endpoint.
 */
class HttpTransport
{
    /**
     * @var \React\HttpClient\Client
     */
    private $client;

    /**
     * @var \Linkorb\HL7\HTTP\HttpResponseHandlerFactory
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