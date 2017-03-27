<?php

namespace LinkORB\HL7\Transport\Process;

use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class ProcessFactory
{
    private $commandline;
    private $eventLoop;

    public function __construct(LoopInterface $eventLoop, $commandline)
    {
        $this->commandline = $commandline;
        $this->eventLoop = $eventLoop;
    }

    public function create()
    {
        $process = new Process($this->commandline);
        $process->start($this->eventLoop);
        return $process;
    }
}
