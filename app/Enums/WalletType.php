<?php

namespace App\Enums;

enum WalletType: string
{
    case Customer = 'customer';
    case Platform = 'platform';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
