<?php

namespace Library\Service;

use Library\Client\DnsResolverClient;

class DnsProviderDetector
{
    const PROVIDER_CLOUDFLARE = 'cloudflare';
    const PROVIDER_PLESK = 'plesk';
    const PROVIDER_UNKNOWN = 'unknown';

    /** @var DnsResolverClient */
    private $resolver;

    /** @var PleskDnsService */
    private $pleskDnsService;

    public function __construct(?DnsResolverClient $resolver = null, ?PleskDnsService $pleskDnsService = null)
    {
        $this->resolver = $resolver ?: new DnsResolverClient();
        $this->pleskDnsService = $pleskDnsService ?: new PleskDnsService();
    }

    public function detect($domainName)
    {
        $publicNs = $this->normalizeNameservers($this->resolver->getNsRecords($domainName));
        if (empty($publicNs)) {
            return self::PROVIDER_PLESK;
        }

        foreach ($publicNs as $ns) {
            if (substr($ns, -strlen('.ns.cloudflare.com')) === '.ns.cloudflare.com') {
                return self::PROVIDER_CLOUDFLARE;
            }
        }

        $pleskNs = $this->normalizeNameservers($this->getPleskNameservers($domainName));
        if (!empty($pleskNs) && count(array_intersect($publicNs, $pleskNs)) > 0) {
            return self::PROVIDER_PLESK;
        }

        return self::PROVIDER_UNKNOWN;
    }

    private function getPleskNameservers($domainName)
    {
        $nameservers = array();
        foreach ($this->pleskDnsService->getExistingRecords($domainName) as $record) {
            if (strtoupper($record->type) === 'NS') {
                $nameservers[] = $record->value;
            }
        }

        return $nameservers;
    }

    private function normalizeNameservers(array $nameservers)
    {
        $normalized = array();
        foreach ($nameservers as $nameserver) {
            $nameserver = strtolower(rtrim(trim($nameserver), '.'));
            if ($nameserver !== '') {
                $normalized[] = $nameserver;
            }
        }

        return array_values(array_unique($normalized));
    }
}
