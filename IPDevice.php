<?php

declare(strict_types=1);

namespace Network;

use Network\NetSwitch\Exception\InvalidArgumentException;

/**
 * Represent a device using IP protocol
 */
class IPDevice
{
    /** @var string  */
    protected $ip;
    /** @var string  */
    protected $hostname;

    /**
     * Constructor
     *
     * @param string $ip       The IP address of the device
     * @param string $hostname The hostname of the device
     *
     * @throws  InvalidArgumentException If the host name is empty
     * @throws  InvalidArgumentException If the IP address is invalid
     */
    public function __construct(string $ip, string $hostname)
    {
        $hostname = trim($hostname);
        if ('' == $hostname) {
            throw new InvalidArgumentException("Host name can not be empty");
        }
        if (false == filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("You must provide a valid IP address");
        }
        $this->ip       = $ip;
        $this->hostname = $hostname;
    }
    
    /**
     * Get the IP address of the device
     * 
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }
    
    /**
     * Get the hostname of the device
     * 
     * @return string
     */
    public function getHostname(): string
    {
        return $this->hostname;
    }
}
