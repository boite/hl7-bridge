<?php

namespace Linkorb\HL7\MLLP;

/**
 * Handle MLLP data from connected clients.
 */
class MllpRequestHandler
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
     * Parse state: inside an MLLP transported message.
     */
    const IN = 1;

    /**
     * Parse state: not inside an MLLP transported message.
     */
    const OUT = 0;

    private $buffer = '';

    /**
     * Add data to the buffer and return messages parsed from MLLP packets.
     *
     * @param string $data
     *
     * @return string[]
     */
    public function handleMllpData($data)
    {
        $this->buffer .= $data;
        return $this->processBuffer();
    }

    /*
     * This function extracts whole messages from MLLP data in the buffer.
     *
     * It works by inspecting, in turn, each character in the buffer and updates
     * a state machine to keep track of the mllp header and trailers that enclose
     * messages.
     *
     * The buffer is emptied of those data corresponding to whole messages,
     * leaving the unprocessed data in the buffer.
     */
    private function processBuffer()
    {
        $messages = [];

        $process_ptr = 0; // pointer into buffer, advances to end of processed msgs
        $state = self::OUT;

        $rchars = array_reverse(str_split($this->buffer)); // pop is quicker than unshift
        $buflen = sizeof($rchars);
        $message = []; // characters of current message

        for ($i = 0; $i < $buflen; $i++) {
            $c = array_pop($rchars);
            if ($state == self::IN && (self::HEADER != $c && self::TRAILER != $c)) {
                array_push($message, $c);
            } elseif ($state == self::IN && self::TRAILER == $c) {
                $state = self::OUT;
                if (!empty($message)) {
                    $messages[] = implode($message);
                    $process_ptr = $i;
                    $message = [];
                }
            } elseif ($state == self::OUT && self::HEADER == $c) {
                $state = self::IN;
            }
        }

        if ($process_ptr) {
            $this->buffer = substr($this->buffer, $process_ptr);
        }

        return $messages;
    }
}
