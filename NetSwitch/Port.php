<?php

declare(strict_types = 1);

namespace Network\NetSwitch;

use Network\NetSwitch\Exception\RuntimeException;
use Network\NetSwitch\Exception\UnexpectedValueException;

/**
 * Represent a port of a NetSwitch
 */
class Port
{
    const ENABLED = true;
    const DISABLED = false;

    /** @var  string */
    private $name;
    /** @var  string */
    private $trunkName;
    /** @var  bool */
    private $enabled;
    /** @var  string */
    private $mode;
    /** @var  PoEConfig */
    private $poe;
    /** @var  \ArrayObject<Vlan> */
    private $vlanList;

    /**
     * Constructor
     *
     * @param string    $name      The name of the port
     * @param bool      $enabled   True if the port is enabled, false otherwhise
     * @param string    $mode      The mode of the port if it's known, null otherwise
     * @param string    $trunkName The name of the trunk if the port is in a trunk, null otherwhise
     * @param PoEConfig $poe       The Poe config of the port
     *
     * @throws UnexpectedValueException If the port name is empty
     */
    public function __construct(
        string $name, bool $enabled, string $mode = null, string $trunkName = null, PoEConfig $poe = null
    )
    {
        if (is_string($mode)) {
            $this->setMode($mode);
        }

        $this->name = $name;
        $this->trunkName = $trunkName;
        if (is_string($trunkName)) {
            $this->setTrunkName($trunkName);
        }
        if ($poe instanceof PoEConfig) {
            $this->setPoe($poe);
        }
        $this->enabled = $enabled;
        $this->vlanList = new \ArrayObject;
    }

    /**
     * Get the list of vlans
     *
     * @return Vlan[]
     */
    public function getVlanList(): \ArrayObject
    {
        return $this->vlanList;
    }

    /**
     * Indicates if the PoE config has been set
     *
     * @return bool
     */
    public function hasPoeConfig(): bool
    {
        return $this->poe instanceof PoEConfig;
    }

    /**
     * Get the PoE config
     *
     * @return PoEConfig
     *
     * @throws RuntimeException If the PoE Config has not been set
     */
    public function getPoeConfig(): PoEConfig
    {
        if (false == $this->hasPoeConfig()) {
            throw new RuntimeException("Cannot get the poe config if the poe config has not been set");
        }

        return $this->poe;
    }

    /**
     * Define the PoE Config of the port
     *
     * The original will be cloned since we want to ensure that two ports won't have the same
     * PoEConfig instance
     *
     * @param PoEConfig $poe The PoEConfig instance
     */
    public function setPoeConfig(PoEConfig $poe)
    {
        $this->poe = clone $poe;
    }

    /**
     * Enable or disable the port
     *
     * @param bool $enabled True if the port has to been enabled, false otherwise
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Enable the port
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Disabled the port
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Indicates if the port is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the name of the port
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Indicate if the port is in trunk
     *
     * @return bool
     */
    public function isInTrunk(): bool
    {
        return is_string($this->trunkName);
    }

    /**
     * Get the name of the trunk if the port is in a trunk
     *
     * @return string
     *
     * @throws RuntimeException If the port is not in a trunk
     */
    public function getTrunkName(): string
    {
        if (false == $this->isInTrunk()) {
            throw new RuntimeException("Cannot get the trunk name if the port is not in a trunk");
        }

        return $this->trunkName;
    }

    /**
     * Set the trunk name the port is in
     *
     * @param string $trunkName The trunk name
     *
     * @throws UnexpectedValueException If the trunk name is empty
     */
    public function setTrunkName(string $trunkName)
    {
        $trunkName = trim($trunkName);
        if ('' === $trunkName) {
            throw new UnexpectedValueException("Trunk name cannot be empty");
        }

        $this->trunkName = $trunkName;
    }

    /**
     * Indicates if the port mode is known
     *
     * @return bool
     */
    public function hasKnownMode(): bool
    {
        return '' != $this->mode;
    }

    /**
     * Get the mode of the port
     *
     * @return string
     */
    public function getMode(): string
    {
        if (false == $this->hasKnownMode()) {
            throw new RuntimeException("Cannot get the port mode mode if the port has not known mode");
        }

        return $this->mode;
    }

    /**
     * Set the mode of the port
     *
     * @param string $mode The mode of the port
     */
    public function setMode(string $mode)
    {
        $mode = trim($mode);
        if ('' === $mode) {
            throw new UnexpectedValueException("Port mode cannot be empty");
        }

        $this->mode = $mode;
    }
}
