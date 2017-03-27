<?php

namespace LinkORB\HL7\Command;

use LinkORB\HL7\Bridge;

interface BridgeAwareInterface
{
    /**
     * @param \LinkORB\HL7\Bridge $bridge
     * @return void
     */
    public function setBridge(Bridge $bridge);

    /**
     * @return \LinkORB\HL7\Bridge
     */
    public function getBridge();
}
