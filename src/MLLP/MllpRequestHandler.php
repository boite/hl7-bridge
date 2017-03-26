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
     * It works by advancing along the buffer and locating header and trailer
     * characters.
     *
     * The buffer is emptied of those data corresponding to whole messages and
     * extraneous characters, leaving the unprocessed data in the buffer.
     */
    private function processBuffer()
    {
        $messages = [];

        $start_ptr = 0; // pointer into buffer, advances to header before message
        $end_ptr = 0;   // pointer into buffer, advances to trailer after message
        $process_ptr = 0; // pointer into buffer, advances with complete/invalid msgs

        while (true) {

            $start_ptr = strpos($this->buffer, self::HEADER, $start_ptr);
            if ($start_ptr === false) {
                break;
            }
            $process_ptr = $start_ptr;
            $end_ptr = strpos($this->buffer, self::TRAILER, 1 + $start_ptr);
            if ($end_ptr === false) {
                break;
            }

            // messages might have been incorrectly terminated. drop them.
            $sub_ptr = $start_ptr;
            while (true) {
                $sub_ptr = strpos($this->buffer, self::HEADER, 1 + $sub_ptr);
                if ($sub_ptr === false || $end_ptr < $sub_ptr) {
                    break; // no next header or next header is beyond trailer
                } else {
                    $start_ptr = $sub_ptr; // advance to next header
                }
            }
            $process_ptr = $start_ptr;

            $len = ($end_ptr - $start_ptr) - 1;
            if ($len > self::MAX_MESSAGE_LEN) {
                $process_ptr = $end_ptr;
            } elseif ($len > 0) {
                array_push($messages, substr($this->buffer, 1 + $start_ptr, $len));
                $process_ptr = $end_ptr;
            }

            $start_ptr = 1 + $end_ptr;

        }

        if ($process_ptr) {
            $this->buffer = substr($this->buffer, $process_ptr);
        }

        return $messages;
    }
}
