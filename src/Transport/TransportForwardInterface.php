<?php

namespace LinkORB\HL7\Transport;

use React\Socket\ConnectionInterface;

interface TransportForwardInterface
{
    /**
     * Forward the supplied message and return the response to the supplied
     * connection.
     *
     * @param string $message
     * @param \React\Socket\ConnectionInterface $connFomMllpClient
     *
     * @return void
     */
    public function forward($message, ConnectionInterface $connFomMllpClient);
}
