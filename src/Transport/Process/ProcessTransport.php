<?php

namespace LinkORB\HL7\Transport\Process;

use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;

use LinkORB\HL7\Transport\TransportForwardInterface;

/**
 * Forward messages to STDIN of a Process.
 */
class ProcessTransport implements TransportForwardInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \LinkORB\HL7\Transport\Process\ProcessFactory
     */
    private $processFactory;

    /**
     * @var \LinkORB\HL7\Transport\Process\ProcessResponseHandlerFactory
     */
    private $processResponseHandlerFactory;

    /**
     * @param ProcessFactory $processFactory
     * @param ProcessResponseHandlerFactory $processResponseHandlerFactory
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        ProcessFactory $processFactory,
        ProcessResponseHandlerFactory $processResponseHandlerFactory,
        LoggerInterface $logger
    ) {
        $this->processFactory = $processFactory;
        $this->processResponseHandlerFactory = $processResponseHandlerFactory;
        $this->logger = $logger;
    }

    /**
     * Forward messages to a Process and set-up a handler for each response.
     *
     * @param \React\Socket\ConnectionInterface $conn
     * @param string $message
     * @return void
     */
    public function forward(ConnectionInterface $conn, $message)
    {
        $messageSize = strlen($message);
        $this->logger->debug("Forward HL7 message of {$messageSize} bytes.");

        $responseHandler = $this->processResponseHandlerFactory->create();
        $process = $this->processFactory->create();

        $process->stdout->on(
            'data',
            function ($data, $stream) use ($responseHandler) {
                $responseHandler->handleResponseData($data);
            }
        );
        $process->on(
            'exit',
            function ($code, $foo) use ($conn, $responseHandler) {
                if ($code !== 0) {
                    return;
                }
                $responseHandler->handleResponseCompletion($conn);
            }
        );

        $process->stdin->end($message);
    }
}
