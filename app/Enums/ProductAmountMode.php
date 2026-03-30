<?php

namespace App\Enums;

enum ProductAmountMode: string
{
    case Fixed = 'fixed';
    case Custom = 'custom';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
