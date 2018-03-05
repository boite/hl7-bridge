<?php

namespace LinkORB\HL7\Transport\Process;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;

use LinkORB\HL7\Transport\Mllp\MllpTransport;
use LinkORB\HL7\Transport\Process\ProcessFactory;
use LinkORB\HL7\Transport\Process\ProcessResponseHandlerFactory;
use LinkORB\HL7\Transport\Process\ProcessTransport;
use LinkORB\HL7\Transport\TransportBuilderInterface;

class ProcessTransportBuilder implements TransportBuilderInterface, LoggerAwareInterface
{
    const NAME = 'process';

    use LoggerAwareTrait;

    private $processCmd;

    public function getName()
    {
        return self::NAME;
    }

    public function init(OutputInterface $output, $config)
    {
        if (!isset($config['process_commandline'])) {
            $output->writeln(
                'Stop! The configuration must include the Process command line.'
            );
            exit(1);
        } else {
            $this->processCmd = $config['process_commandline'];
        }
    }

    public function build(LoopInterface $loop)
    {
        return new ProcessTransport(
            new ProcessFactory($loop, $this->processCmd),
            new ProcessResponseHandlerFactory(new MllpTransport($this->logger)),
            $this->logger
        );
    }
}
