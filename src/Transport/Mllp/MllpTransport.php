<?php

namespace LinkORB\HL7\Transport\Mllp;

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

    /**
     * Return an ACK along the MLLP transport.
     *
     * @param \React\Socket\ConnectionInterface $conn
     * @param string $message
     * @return void
     */
    public function acknowledge(ConnectionInterface $conn, $message)
    {
        $conn->pause();
        $conn->write(sprintf("%s%s%s\r", self::HEADER, $message, self::TRAILER));
        $conn->end();
    }
}
