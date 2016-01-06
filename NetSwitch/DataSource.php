<?php

declare(strict_types = 1);

namespace Network\NetSwitch;

/**
 * Represent a data source which will be used to provide information about the device behind a mac address
 */
class DataSource
{
    const IP_LOOKUP = true;
    const NO_IP_LOOKUP = false;

    /** @var  string */
    private $name;
    /** @var  array */
    private $data;
    /** @var  bool */
    private $ipLookup;

    /**
     * Constructor
     *
     * @param string $name     The name of the data source
     * @param array  $data     The data of the data source
     * @param bool   $ipLookup Indicates if we have to lookup for an IP address
     */
    public function __construct(string $name, array $data, bool $ipLookup = false)
    {
        $this->name = $name;
        $this->data = $data;
        $this->ipLookup = $ipLookup;
    }

    /**
     * Get the name of the datasource
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the data of the datasource
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Indicates if the datasource needs an ip lookup
     *
     * @return bool
     */
    public function needIpLookup(): bool
    {
        return $this->ipLookup;
    }
}
