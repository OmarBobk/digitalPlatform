<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Fulfilled = 'fulfilled';
    case Failed = 'failed';

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
