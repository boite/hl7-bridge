<?php

namespace LinkORB\HL7\Transport;

use React\Socket\ConnectionInterface;

use LinkORB\HL7\Transport\Mllp\MllpTransport;

/**
 * Handle a single response, passing it to MllpTransport.
 */
abstract class AbstractResponseHandler implements ResponseHandlerInterface
{
    /**
     * @var \LinkORB\HL7\Transport\Mllp\MllpTransport
     */
    protected $mllpTransport;

    /**
     * @var string
     */
    protected $buffer = '';

    public function __construct(MllpTransport $mllpTransport)
    {
        $this->mllpTransport = $mllpTransport;
    }

    public function handleResponseData($data)
    {
        $this->buffer .= $data;
    }

    public function handleResponseCompletion(ConnectionInterface $conn)
    {
        $this->mllpTransport->acknowledge($conn, $this->buffer);
    }
}
