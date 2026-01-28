<?php

namespace App\Enums;

enum FulfillmentLogLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

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
