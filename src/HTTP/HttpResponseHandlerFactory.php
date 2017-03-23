<?php

namespace Linkorb\HL7\HTTP;

use Linkorb\HL7\MLLP\MllpTransport;

/**
 * Factory to create HttpResponseHandler instances, complete with the required
 * MllpTransport.
 */
class HttpResponseHandlerFactory
{
    /**
     * @var \Linkorb\HL7\MLLP\MllpTransport
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