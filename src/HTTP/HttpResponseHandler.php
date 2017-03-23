<?php

namespace Linkorb\HL7\HTTP;

use React\Socket\ConnectionInterface;

use Linkorb\HL7\MLLP\MllpTransport;

/**
 * Handle a single HTTP response.
 */
class HttpResponseHandler
{
    /**
     * @var \Linkorb\HL7\MLLP\MllpTransport
     */
    private $mllpTransport;

    private $buffer = '';

    public function __construct(MllpTransport $mllpTransport)
    {
        $this->mllpTransport = $mllpTransport;
    }

    /**
     * Add data from a single HTTP response to the buffer.
     *
     * @param string $data
     */
    public function handleResponseData($data)
    {
        $this->buffer .= $data;
    }

    /**
     * Pass a socket and HTTP response data (ACK) to the MllpTransport.
     *
     * @param \React\Socket\ConnectionInterface $conn Connection from MLLP client.
     */
    public function handleResponseCompletion(ConnectionInterface $conn)
    {
        $this->mllpTransport->acknowledge($conn, $this->buffer);
    }
}
