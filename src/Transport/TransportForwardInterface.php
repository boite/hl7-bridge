<?php

namespace LinkORB\HL7\Transport;

use React\Socket\ConnectionInterface;

interface TransportForwardInterface
{
    /**
     * Forward the supplied message and return the response to the supplied
     * connection.
     *
     * @param \React\Socket\ConnectionInterface $conn
     * @param string $message
     * @return void
     */
    public function forward(ConnectionInterface $conn, $message);
}
