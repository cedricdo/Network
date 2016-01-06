<?php

declare(strict_types = 1);

namespace Network;

/**
 * Represent an object which will aggregate multiple arp data
 */
class ARPAggregator
{
    /** @var array  */
    private $data;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->data = [];
    }

    /**
     * Add arp data to the aggregate
     *
     * @param array $arp The arp data
     */
    public function addArp(array $arp)
    {
        foreach ($arp as $mac => $ips) {
            foreach ($ips as $ip) {
                if (!isset($this->data[$mac][$ip['ip']])) {
                    if (!isset($this->data[$mac])) {
                        $this->data[$mac] = [];
                    }
                    $this->data[$mac][$ip['ip']] = $ip;
                }
            }
        }
    }

    /**
     * Get the aggregated data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
