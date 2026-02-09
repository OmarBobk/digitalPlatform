<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
