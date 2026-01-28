<?php

namespace App\Enums;

enum TopupMethod: string
{
    case ShamCash = 'sham_cash';
    case EftTransfer = 'eft_transfer';

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
