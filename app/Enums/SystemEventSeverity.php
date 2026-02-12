<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Severity discipline: default info; financial events info;
 * reconciliation drift warning; system failure critical.
 */
enum SystemEventSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
