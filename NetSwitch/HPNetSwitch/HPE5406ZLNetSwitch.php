<?php

declare(strict_types=1);

namespace Network\NetSwitch\HPNetSwitch;

/**
 * Represent an HP switch model E5406 ZL
 */
class HPE5406ZLNetSwitch extends HPNetSwitch
{
    /**
     * {@inheritdoc}
     */
    public function getModel(): string
    {
        return 'HP E5406 ZL';
    }
}
