<?php

declare(strict_types = 1);

namespace Network\NetSwitch;

/**
 * Represent a network VLAN
 */
class Vlan
{
    const NEIGHBORS = -1;

    /** @var  int */
    private $id;
    /** @var  string */
    private $name;
    /** @var  \ArrayObject */
    private $macAddressList;

    /**
     * Constructor
     *
     * @param int    $id The ID of the vlan
     * @param string $name The name of the vlan
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->setName($name);
        $this->macAddressList = new \ArrayObject;
    }


    /**
     * Get the id of the vlan
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the name of the vlan
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Defines the name of the vlan
     *
     * @param string $name The name of the vlan
     */
    public function setName(string $name)
    {
        $this->name = trim($name);
    }

    /**
     * Get the list of the mac addresses
     *
     * @return \ArrayObject
     */
    public function getMacAddressList(): \ArrayObject
    {
        return $this->macAddressList;
    }

    /**
     * The __clone method is required since vlan will be cloned
     */
    public function __clone()
    {
        $this->macAddressList = clone $this->macAddressList;
    }
}
