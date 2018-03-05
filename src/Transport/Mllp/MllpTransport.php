<?php

namespace LinkORB\HL7\Transport\Mllp;

use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;

/**
 * Send messages, over MLLP, back to a connected client.
 *
 * It is intended that a single long-lived object of this class is all that is
 * needed to send MLLP encapsulated messages back to clients.
 */
class MllpTransport
{
    /**
     * MLLP Header
     */
    const HEADER = "\x0B";

    /**
     * MLLP Trailer
     */
    const TRAILER = "\x1C";

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return an ACK along the MLLP transport.
     *
     * @param \React\Socket\ConnectionInterface $conn
     * @param string $message
     * @return void
     */
    public function acknowledge(ConnectionInterface $conn, $message)
    {
        $messageSize = strlen($message);
        $this->logger->debug("Acknowledge MLLP frame with HL7 ACK of {$messageSize} bytes.");
        $conn->pause();
        $conn->write(sprintf("%s%s%s\r", self::HEADER, $message, self::TRAILER));
        $conn->end();
    }
}
