<?php

namespace LinkORB\HL7\Command;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Factory as EventLoopFac;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use LinkORB\HL7\Bridge;
use LinkORB\HL7\Transport\Mllp\MllpRequestHandler;

class BridgeCommand extends Command implements BridgeAwareInterface
{
    const CONFIG_DIRNAME = 'config';
    const CONFIG_FILENAME = 'bridge.yml';

    const CONF_LISTEN_ADDR = '127.0.0.1';
    const CONF_LISTEN_PORT = 2575;

    const DEFAULT_TRANSPORT = 'process';

    private $bridge;
    private $configPath;
    private $socket;
    private $transportBuilder;

    public function __construct($name, $rootPath)
    {
        $this->configPath = $rootPath
            . DIRECTORY_SEPARATOR . self::CONFIG_DIRNAME
            . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME
        ;

        parent::__construct($name);
    }

    public function setBridge(Bridge $bridge)
    {
        $this->bridge = $bridge;
    }

    public function getBridge()
    {
        return $this->bridge;
    }

    protected function configure()
    {
        $this
            ->setDescription(
                'Accept connections from MLLP clients, forward their messages over the selected transport and relay the acknowledgments back to the MLLP clients.'
            )
            ->addOption(
                'configfile',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to an alternative configuration file.'
            )
            ->addOption(
                'listen_addr',
                'l',
                InputOption::VALUE_REQUIRED,
                'IP Address of the network interface on which to listen.'
            )
            ->addOption(
                'listen_port',
                'p',
                InputOption::VALUE_REQUIRED,
                'TCP Port Number on which to listen.'
            )
            ->addOption(
                'transport',
                't',
                InputOption::VALUE_REQUIRED,
                'Use the named transport backend (e.g. http, process).',
                self::DEFAULT_TRANSPORT
            )
            ->addOption(
                'logfile',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to which to write a debug log file.'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $configPath = $this->configPath;
        if ($input->getOption('configfile')) {
            $configPath = $input->getOption('configfile');
        }
        if (!is_file($configPath)) {
            $output->writeln(
                sprintf(
                    'Stop! The configuration file cannot be found at "%s".',
                    $configPath
                )
            );
            exit(1);
        } elseif (!is_readable($configPath)) {
            $output->writeln(
                sprintf(
                    'Stop! The configuration file cannot be read from "%s".',
                    $configPath
                )
            );
            exit(1);
        }
        if (false === ($configData = file_get_contents($configPath))) {
            $output->writeln(
                sprintf(
                    'Stop! The configuration file cannot be read from "%s".',
                    $configPath
                )
            );
            exit(1);
        }
        try {
            $config = Yaml::parse($configData);
        } catch (ParseException $e) {
            $output->writeln(
                sprintf(
                    'Stop! The configuration file does not seem to be valid: %s.',
                    $e->getMessage()
                )
            );
            exit(1);
        }

        $sock = self::CONF_LISTEN_ADDR;
        if ($input->getOption('listen_addr')) {
            $sock = $input->getOption('listen_addr');
        } elseif (isset($config['listen_addr'])) {
            $sock = $config['listen_addr'];
        }
        $sock .= ':';
        if ($input->getOption('listen_port')) {
            $sock .= $input->getOption('listen_port');
        } elseif (isset($config['listen_port'])) {
            $sock .= $config['listen_port'];
        } else {
            $sock .= self::CONF_LISTEN_PORT;
        }
        $this->socket = $sock;

        $logHandler = null;
        $debugLog = $input->getOption('logfile');
        if (!$debugLog && isset($config['logfile'])) {
            $debugLog = $config['logfile'];
        }
        if ($debugLog) {
            $logHandler = new StreamHandler($debugLog, Logger::DEBUG);
        } else {
            $logHandler = new NullHandler(Logger::DEBUG);
        }
        $this->getBridge()->getLogger()->pushHandler($logHandler);

        $transports = $this->getBridge()->getRegisteredTransports();
        if (!array_key_exists($input->getOption('transport'), $transports)) {
            $output->writeln(
                sprintf(
                    'Stop! The transport can be one of: %s.',
                    implode(', ', array_keys($transports))
                )
            );
            exit(1);
        }
        $this->transportBuilder = $transports[$input->getOption('transport')];
        $this->transportBuilder->init($output, $config);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = EventLoopFac::create();

        $transport = $this->transportBuilder->build($loop);

        $server = new Server($this->socket, $loop);

        $server->on(
            'connection',
            function (ConnectionInterface $conn) use ($transport) {
                $mmlpRequestHandler = new MllpRequestHandler();
                $conn->on(
                    'data',
                    function ($data, $conn) use ($mmlpRequestHandler, $transport) {
                        foreach ($mmlpRequestHandler->handleMllpData($data) as $message) {
                            $transport->forward($conn, $message);
                        }
                    }
                );
            }
        );

        if ($output->isDebug()) {
            $loop->addPeriodicTimer(
                10,
                function () use ($output) {
                    $output->writeln(
                        sprintf(
                            '[BRIDGE] Real Memory: %s bytes; Used Memory: %s bytes.',
                            number_format((float) memory_get_usage(true)),
                            number_format((float) memory_get_usage())
                        )
                    );
                }
            );
        }

        $output->writeln(
            '[BRIDGE] Listening on ' . $this->socket,
            OutputInterface::VERBOSITY_DEBUG
        );

        $loop->run();
    }
}
