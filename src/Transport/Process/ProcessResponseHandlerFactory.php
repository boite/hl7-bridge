<?php

namespace LinkORB\HL7\Transport\Process;

use LinkORB\HL7\Transport\Mllp\MllpTransport;

/**
 * Factory to create ProcessResponseHandler instances, complete with the
 * required MllpTransport.
 */
class ProcessResponseHandlerFactory
{
    /**
     * @var \LinkORB\HL7\Transport\Mllp\MllpTransport
     */
    private $mllpTransport;

    public function __construct(MllpTransport $mllpTransport)
    {
        $this->mllpTransport = $mllpTransport;
    }

    /**
     * Create an instance of ProcessResponseHandler.
     *
     * @return ProcessResponseHandler
     */
    public function create()
    {
        return new ProcessResponseHandler($this->mllpTransport);
    }
}
