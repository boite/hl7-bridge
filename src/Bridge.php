<?php

namespace LinkORB\HL7;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

use LinkORB\HL7\Command\BridgeAwareInterface;
use LinkORB\HL7\Transport\TransportBuilderInterface;

class Bridge
{
    private $app;
    private $registeredTransports = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function __call($method, $args)
    {
        if (!method_exists($this->app, $method)) {
            throw new Exception(
                sprintf(
                    'The method %s::%s does not exist.',
                    Application::class,
                    $method
                )
            );
        }

        return call_user_func_array(array($this->app, $method), $args);
    }

    public function add(Command $command)
    {
        if ($this->app->add($command)->isEnabled()
            && $command instanceof BridgeAwareInterface
        ) {
            $command->setBridge($this);
        }
        return $command;
    }

    /**
     * Get a map of transport names to builders implementing TransportBuilderInterface.
     *
     * @return array
     */
    public function getRegisteredTransports()
    {
        return $this->registeredTransports;
    }

    /**
     * Register a transport by providing its builder.
     *
     * @param TransportBuilderInterface $transportBuilder
     * @return void
     */
    public function registerTransport(TransportBuilderInterface $transportBuilder)
    {
        $this->registeredTransports[$transportBuilder->getName()] = $transportBuilder;
    }
}
