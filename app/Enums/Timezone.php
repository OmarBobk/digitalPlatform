<?php

namespace App\Enums;

enum Timezone: string
{
    case Syria = 'Asia/Damascus';
    case Turkey = 'Europe/Istanbul';

    /**
     * Get all timezone values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the display name for the timezone.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::Syria => 'Syria',
            self::Turkey => 'Turkey',
        };
    }

    /**
     * Map a timezone string to a Timezone enum.
     * Returns null if no match is found.
     */
    public static function fromTimezoneString(?string $timezone): ?self
    {
        if ($timezone === null) {
            return null;
        }

        return match ($timezone) {
            'Asia/Damascus' => self::Syria,
            'Europe/Istanbul' => self::Turkey,
            default => null,
        };
    }

    /**
     * Map a country code to a Timezone enum.
     * Returns null if no match is found.
     */
    public static function fromCountryCode(?string $countryCode): ?self
    {
        if ($countryCode === null) {
            return null;
        }

        return match ($countryCode) {
            '+963' => self::Syria,
            '+90' => self::Turkey,
            default => null,
        };
    }

    /**
     * Detect timezone from timezone string or country code.
     * Falls back to country code if timezone string doesn't match.
     */
    public static function detect(?string $timezoneString, ?string $countryCode = null): ?self
    {
        $timezone = self::fromTimezoneString($timezoneString);

        if ($timezone !== null) {
            return $timezone;
        }

        return self::fromCountryCode($countryCode);
    }
}
