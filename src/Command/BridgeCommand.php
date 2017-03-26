<?php

namespace Linkorb\HL7\Command;

use React\Dns\Resolver\Factory as DnsResolverFac;
use React\EventLoop\Factory as EventLoopFac;
use React\HttpClient\Client;
use React\HttpClient\Factory as HttpClientFac;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use Linkorb\HL7\HTTP\HttpResponseHandlerFactory;
use Linkorb\HL7\HTTP\HttpTransport;
use Linkorb\HL7\MLLP\MllpRequestHandler;
use Linkorb\HL7\MLLP\MllpTransport;

class BridgeCommand extends Command
{
    const CONFIG_DIRNAME = 'config';
    const CONFIG_FILENAME = 'bridge.yml';

    const CONF_LISTEN_ADDR = '127.0.0.1';
    const CONF_LISTEN_PORT = 2575;

    private $configPath;
    private $socket;
    private $dnsAddress;
    private $endpointUrl;

    public function __construct($name, $rootPath)
    {
        $this->configPath = $rootPath
            . DIRECTORY_SEPARATOR . self::CONFIG_DIRNAME
            . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME
        ;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription(
                'Accept connections from MLLP clients, forward their messages to an HTTP end point and relay the acknowledgments back to the MLLP clients.'
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

        if (!isset($config['dns_resolver_addr'])) {
            $output->writeln(
                'Stop! The configuration must include the IP address of a DNS resolver.'
            );
            exit(1);
        } else {
            $this->dnsAddress = $config['dns_resolver_addr'];
        }

        if (!isset($config['http_endpoint_url'])) {
            $output->writeln(
                'Stop! The configuration must include the HTTP endpoint URL.'
            );
            exit(1);
        } else {
            $this->endpointUrl = $config['http_endpoint_url'];
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = EventLoopFac::create();

        $dnsResolverFactory = new DnsResolverFac();
        $dnsResolver = $dnsResolverFactory->createCached($this->dnsAddress, $loop);

        $clientFactory = new HttpClientFac();
        $client = $clientFactory->create($loop, $dnsResolver);

        $httpTransporter = new HttpTransport(
            $this->endpointUrl,
            $client,
            new HttpResponseHandlerFactory(new MllpTransport)
        );

        $server = new Server($this->socket, $loop);

        $server->on(
            'connection',
            function (ConnectionInterface $conn) use ($httpTransporter) {
                $mmlpRequestHandler = new MllpRequestHandler();
                $conn->on(
                    'data',
                    function($data, $conn) use ($mmlpRequestHandler, $httpTransporter) {
                        foreach ($mmlpRequestHandler->handleMllpData($data) as $message) {
                            $httpTransporter->forward($conn, $message);
                        }
                    }
                );
            }
        );

        if ($output->isDebug()) {
            $loop->addPeriodicTimer(
                10,
                function() use ($output) {
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
