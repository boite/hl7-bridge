<?php

namespace LinkORB\HL7\Transport;

use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface TransportBuilderInterface
{
    /**
     * Get the name of the transport.
     *
     * @return string
     */
    public function getName();

    /**
     * Initialise the transport.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array $config
     * @return void
     */
    public function init(OutputInterface $output, $config);

    /**
     * Build the transport.
     *
     * @param \React\EventLoop\LoopInterface $loop
     * @return TransportForwardInterface
     */
    public function build(LoopInterface $loop);
}
