<?php

declare(strict_types = 1);

namespace Network\NetSwitch;
use Network\NetSwitch\Exception\RangeException;

/**
 * Represent the power over ethernet config of a port
 */
class PoEConfig
{
    const ENABLED = true;
    const DISABLED = false;

    /** @var  bool */
    private $enabled;
    /** @var  float */
    private $usage;
    /** @var  float */
    private $max;

    /**
     * Constructor
     *
     * @param bool  $enabled True if the PoE is enabled, false otherwise
     * @param float $max     The max PoE power
     * @param float $usage   The current PoE usage
     */
    public function __construct(bool $enabled, float $max, float $usage)
    {
        $this->setEnabled($enabled);
        $this->setMax($max);
        $this->setUsage($usage);
    }

    /**
     * Indicates if the poe is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable the poe
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Disabled the poe
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Enable or disable the poe
     *
     * @param bool $enabled True if the poe has to been enabled, false otherwise
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Get the maximum PoE usage
     *
     * @return float
     */
    public function getMax(): float
    {
        return $this->max;
    }

    /**
     * Define the maximum PoE usage
     *
     * @param float $$max The maximum PoE usage
     *
     * @throws RangeException If $poeMax is negative
     * @throws RangeException If $poeMax is lesser than poe usage
     */
    public function setMax(float $max)
    {
        if (0 >= $max) {
            throw new RangeException("PoE Max cannot be negative");
        }
        if ($max < $this->usage) {
            throw new RangeException("PoE Max cannot be lesser than usage");
        }

        $this->max = $max;
    }

    /**
     * Get the PoE usage
     *
     * @return float
     */
    public function getUsage(): float
    {
        return $this->usage;
    }

    /**
     * Define the current PoE usage
     *
     * @param float $usage The current PoE usage
     *
     * @throws RangeException If the poe usage is negative
     * @throws RangeException If the poe usage is greater than maximum poe
     */
    public function setUsage(float $usage)
    {
        if (0 > $usage) {
            throw new RangeException("PoE Usage cannot be negative");
        }
        if ($usage > $this->max) {
            throw new RangeException("poE Usage cannot be greater than max ");
        }

        $this->usage = $usage;
    }
}
