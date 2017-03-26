<?php

namespace Linkorb\HL7\MLLP;

/**
 * Handle MLLP data from connected clients.
 */
class MllpRequestHandler
{
    /**
     * Maximum permitted message length.
     *
     * @var integer
     */
    const MAX_MESSAGE_LEN = 2097152;

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

        $process_ptr = 0; // pointer into buffer, advances with complete/invalid msgs
        $state = self::OUT;

        $buflen = strlen($this->buffer);
        $message = ''; // characters of current message

        for ($i = 0; $i < $buflen; $i++) {

            $c = substr($this->buffer, $i, 1);

            if ($state == self::IN && (self::HEADER != $c && self::TRAILER != $c)) {
                $message .= $c;
                if (strlen($message) > self::MAX_MESSAGE_LEN) {
                    // encountered extra long message. so long message.
                    $state = self::OUT;
                    $process_ptr = $i;
                    $message = '';
                }
            } elseif ($state == self::IN && self::TRAILER == $c) {
                $state = self::OUT;
                if (strlen($message)) {
                    $messages[] = $message;
                    $process_ptr = $i;
                    $message = '';
                }
            } elseif ($state == self::IN && self::HEADER == $c) {
                // encountered abrupt end of message. au revoir message.
                $process_ptr = $i-1;
                $message = '';
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
