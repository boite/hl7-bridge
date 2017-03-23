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

use Linkorb\HL7\HTTP\HttpResponseHandlerFactory;
use Linkorb\HL7\HTTP\HttpTransport;
use Linkorb\HL7\MLLP\MllpRequestHandler;
use Linkorb\HL7\MLLP\MllpTransport;

class BridgeCommand extends Command
{
    const CONF_LISTEN_ADDR = '127.0.0.1';
    const CONF_LISTEN_PORT = 2575;
    const CONF_DNS_HOST = '127.0.0.1';
    const CONF_HTTP_ENDPOINT_URL = 'http://127.0.0.1:8910/';

    protected function configure()
    {
        $this
            ->setDescription(
                'Accept connections from MLLP clients, forward their messages to an HTTP end point and relay the acknowledgments back to the MLLP clients.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = EventLoopFac::create();

        $dnsResolverFactory = new DnsResolverFac();
        $dnsResolver = $dnsResolverFactory->createCached(self::CONF_DNS_HOST, $loop);

        $clientFactory = new HttpClientFac();
        $client = $clientFactory->create($loop, $dnsResolver);

        $mmlpRequestHandler = new MllpRequestHandler();

        $httpTransporter = new HttpTransport(
            self::CONF_HTTP_ENDPOINT_URL,
            $client,
            new HttpResponseHandlerFactory(new MllpTransport)
        );

        $socket = new Server(self::CONF_LISTEN_ADDR . ':' . self::CONF_LISTEN_PORT, $loop);

        $socket->on(
            'connection',
            function (ConnectionInterface $conn) use ($mmlpRequestHandler, $httpTransporter) {
                $conn->on(
                    'data',
                    function($data, $conn) use ($mmlpRequestHandler, $httpTransporter) {
                        $messages = $mmlpRequestHandler->handleMllpData($data);
                        foreach ($messages as $message) {
                            $httpTransporter->forward($conn, $message);
                        }
                    }
                );
            }
        );

        echo '[BRIDGE] Listening on ' . self::CONF_LISTEN_ADDR . ':' . self::CONF_LISTEN_PORT . PHP_EOL;

        $loop->run();
    }
}
