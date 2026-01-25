<?php

namespace App\Enums;

enum WalletTransactionDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    /**
     * Get all enum values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
