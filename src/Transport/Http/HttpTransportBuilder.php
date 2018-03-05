<?php

namespace LinkORB\HL7\Transport\Http;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\Dns\Resolver\Factory as DnsResolverFac;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client;
use React\HttpClient\Factory as HttpClientFac;
use Symfony\Component\Console\Output\OutputInterface;

use LinkORB\HL7\Transport\Http\HttpResponseHandlerFactory;
use LinkORB\HL7\Transport\Http\HttpTransport;
use LinkORB\HL7\Transport\Mllp\MllpTransport;
use LinkORB\HL7\Transport\TransportBuilderInterface;

class HttpTransportBuilder implements TransportBuilderInterface, LoggerAwareInterface
{
    const NAME = 'http';

    use LoggerAwareTrait;

    private $dnsAddress;
    private $endpointUrl;

    public function getName()
    {
        return self::NAME;
    }

    public function init(OutputInterface $output, $config)
    {
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
    }

    public function build(LoopInterface $loop)
    {
        $dnsResolverFactory = new DnsResolverFac();
        $dnsResolver = $dnsResolverFactory->createCached($this->dnsAddress, $loop);

        $clientFactory = new HttpClientFac();
        $client = $clientFactory->create($loop, $dnsResolver);

        return new HttpTransport(
            $this->endpointUrl,
            $client,
            new HttpResponseHandlerFactory(new MllpTransport($this->logger)),
            $this->logger
        );
    }
}
