<?php

declare(strict_types=1);

namespace Network;

/**
 * Represent an object which provide an ARP table
 */
interface ARPProvider
{
    /**
     * Get the ARP table
     * 
     * @return array
     */
    public function getARPTable(): array;
}
