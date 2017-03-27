<?php

namespace LinkORB\HL7\Transport\Http;

use LinkORB\HL7\Transport\Mllp\MllpTransport;

/**
 * Factory to create HttpResponseHandler instances, complete with the required
 * MllpTransport.
 */
class HttpResponseHandlerFactory
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
     * Create an instance of HttpResponseHandler.
     */
    public function create()
    {
        return new HttpResponseHandler($this->mllpTransport);
    }
}
