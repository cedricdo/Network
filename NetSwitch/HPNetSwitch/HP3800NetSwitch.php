<?php

declare(strict_types=1);

namespace Network\NetSwitch\HPNetSwitch;

/**
 * Represent an HP Switch model 3800
 */
class HP3800NetSwitch extends HPNetSwitch
{
    /**
     * {@inheritdoc}
     */
    public function getModel(): string
    {
        return 'HP 3800';
    }
}
