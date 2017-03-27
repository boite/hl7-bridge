<?php

namespace LinkORB\HL7\Transport;

use React\Socket\ConnectionInterface;

/**
 * Handle a single response.
 */
interface ResponseHandlerInterface
{
    /**
     * Add data from a single response to the buffer.
     *
     * @param string $data
     * @return void
     */
    public function handleResponseData($data);

    /**
     * Pass a socket and response data (ACK) to the MllpTransport.
     *
     * @param \React\Socket\ConnectionInterface $conn Connection from MLLP client.
     * @return void
     */
    public function handleResponseCompletion(ConnectionInterface $conn);
}
