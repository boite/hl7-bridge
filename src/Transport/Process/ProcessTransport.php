<?php

namespace LinkORB\HL7\Transport\Process;

use React\Socket\ConnectionInterface;

use LinkORB\HL7\Transport\TransportForwardInterface;

/**
 * Forward messages to STDIN of a Process.
 */
class ProcessTransport implements TransportForwardInterface
{
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
     * @return void
     */
    public function __construct(
        ProcessFactory $processFactory,
        ProcessResponseHandlerFactory $processResponseHandlerFactory
    ) {
        $this->processFactory = $processFactory;
        $this->processResponseHandlerFactory = $processResponseHandlerFactory;
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
