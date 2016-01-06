<?php

declare(strict_types = 1);

namespace Network\NetSwitch;

use Network\NetSwitch\Exception\InvalidArgumentException;
use Network\NetSwitch\Exception\RuntimeException;
use Network\NetSwitch\Exception\UnexpectedValueException;

class PortConnectedDevice
{
    const UNKNOWN_MAC_ADDRESS = '';

    /** @var string  */
    private $macAddress;
    /** @var  string */
    private $ipAddress;
    /** @var  string */
    private $portName;
    /** @var  string */
    private $sysName;
    /** @var  DataSource */
    private $dataSource;
    /** @var  \DateTimeImmutable */
    private $time;

    /**
     * Constructor
     *
     * @param DataSource $dataSource
     * @param string     $macAddress
     */
    public function __construct(DataSource $dataSource, string $macAddress = self::UNKNOWN_MAC_ADDRESS)
    {
        if (self::UNKNOWN_MAC_ADDRESS != $macAddress) {
            $this->setMacAddress($macAddress);
        } else {
            $this->macAddress = self::UNKNOWN_MAC_ADDRESS;
        }
        $this->dataSource = $dataSource;
    }

    /**
     * Indicates if the device has a mac address
     *
     * @return bool
     */
    public function hasMacAddress(): bool
    {
        return self::UNKNOWN_MAC_ADDRESS != $this->macAddress;
    }

    /**
     * Set the mac address of the device
     *
     * @param string $macAddress The mac address of the device
     *
     * @throws InvalidArgumentException If the mac address is invalid
     */
    public function setMacAddress(string $macAddress)
    {
        if (false === filter_var($macAddress, FILTER_VALIDATE_MAC)) {
            throw new InvalidArgumentException("You must provide a valid mac address");
        }

        $this->macAddress = $macAddress;
    }

    /**
     * Get the mac address of the device
     *
     * @return string
     *
     * @throws RuntimeException If the mac address has not been defined
     */
    public function getMacAddress(): string
    {
        if (false == $this->hasMacAddress()) {
            throw new RuntimeException("Mac address undefined");
        }

        return $this->macAddress;
    }

    /**
     * Indicates if the device has an IP address
     *
     * @return bool
     */
    public function hasIpAddress(): bool
    {
        return is_string($this->ipAddress);
    }

    /**
     * Get the ip address of the device
     *
     * @return string
     *
     * @throws RuntimeException if
     */
    public function getIpAddress(): string
    {
        if (false == $this->hasIpAddress()) {
            throw new RuntimeException("IP Address undefined");
        }

        return $this->ipAddress;
    }

    /**
     * Define the ip address of the device
     *
     * @param string $ipAddress The IP address of the device
     *
     * @throws InvalidArgumentException If the ip address is invalid
     */
    public function setIpAddress(string $ipAddress)
    {
        if (false == filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("You must provide valid IP address");
        }

        $this->ipAddress = $ipAddress;
    }

    /**
     * Get the port name of the device
     *
     * @return string
     *
     * @throws RuntimeException If the port name has not been defined
     */
    public function getPortName(): string
    {
        if (false == $this->hasPortName()) {
            throw new RuntimeException("Port name undefined");
        }
        return $this->portName;
    }

    /**
     * Indicates if the port name is defined
     *
     * @return bool
     */
    public function hasPortName(): bool
    {
        return is_string($this->portName);
    }

    /**
     * Define the port name of the device
     *
     * @param string $portName
     *
     * @throws UnexpectedValueException If the port name is empty
     */
    public function setPortName(string $portName)
    {
        $portName = trim($portName);
        if ('' == $portName) {
            throw new UnexpectedValueException("Port name cannot be empty");
        }

        $this->portName = $portName;
    }

    /**
     * Get the sysname of the device
     *
     * @return string
     *
     * @throws RuntimeException If the sysname has not been defined
     */
    public function getSysName(): string
    {
        if (false == $this->hasSysName()) {
            throw new RuntimeException("Sysname undefined");
        }
        return $this->sysName;
    }

    /**
     * Indicates if the device has a sysname
     *
     * @return bool
     */
    public function hasSysName(): bool
    {
        return is_string($this->sysName);
    }

    /**
     * Define the sysname of the device
     *
     * @param string $sysName
     *
     * @throws UnexpectedValueException If the sysname is empty
     */
    public function setSysName(string $sysName)
    {
        $sysName = trim($sysName);
        if ('' == $sysName) {
            throw new UnexpectedValueException("Sysname cannot be empty");
        }

        $this->sysName = $sysName;
    }

    /**
     * Get the data source where the informations comes from
     *
     * @return DataSource
     */
    public function getDataSource(): DataSource
    {
        return $this->dataSource;
    }

    /**
     * @return bool
     */
    public function hasTime(): bool
    {
        return $this->time instanceof \DateTimeImmutable;
    }

    /**
     * @return \DateTimeImmutable
     *
     * @throws RuntimeException If the time has not been defined
     */
    public function getTime(): \DateTimeImmutable
    {
        if (false == $this->hasTime()) {
            throw new RuntimeException("Time undefined");
        }
        return $this->time;
    }

    /**
     * @param \DateTimeImmutable $time
     */
    public function setTime(\DateTimeImmutable $time)
    {
        $this->time = $time;
    }


}
