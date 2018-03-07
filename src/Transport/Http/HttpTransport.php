<?php

namespace LinkORB\HL7\Transport\Http;

use Psr\Log\LoggerInterface;
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

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $url;

    /**
     * @param string $url URL of the HTTP endpoint.
     * @param Client $client An HTTP client.
     * @param HttpResponseHandlerFactory $httpResponseHandlerFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        $url,
        Client $client,
        HttpResponseHandlerFactory $httpResponseHandlerFactory,
        LoggerInterface $logger
    ) {
        $this->url = $url;
        $this->client = $client;
        $this->httpResponseHandlerFactory = $httpResponseHandlerFactory;
        $this->logger = $logger;
    }

    /**
     * Forward messages to an HTTP endpoint and set-up a handler for each response.
     *
     * @param string $message
     * @param ConnectionInterface $connFomMllpClient
     *
     * @return void
     */
    public function forward($message, ConnectionInterface $connFomMllpClient)
    {
        $messageSize = strlen($message);
        $this->logger->debug("Forward HL7 message of {$messageSize} bytes.");

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
            function (Response $response) use ($connFomMllpClient) {
                if ($response->getCode() != 200 && $response->getCode() != 400) {
                    return;
                }
                if (!array_key_exists('Content-Type', $response->getHeaders())) {
                    return;
                }
                if ($response->getHeaders()['Content-Type'] != 'x-application/hl7-v2+er7') {
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
                    function () use ($connFomMllpClient, $responseHandler) {
                        $responseHandler->handleResponseCompletion($connFomMllpClient);
                    }
                );
            }
        );

        $request->end($message);
    }
}
