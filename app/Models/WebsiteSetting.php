<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $table = 'website_settings';

    protected $fillable = [
        'contact_email',
        'primary_phone',
        'secondary_phone',
        'prices_visible',
        'usd_try_rate',
        'usd_try_rate_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prices_visible' => 'boolean',
            'usd_try_rate' => 'decimal:6',
            'usd_try_rate_updated_at' => 'datetime',
        ];
    }

    /**
     * Get the singleton instance (first row). Creates one with defaults if none exists.
     */
    public static function instance(): self
    {
        $row = self::query()->first();
        if ($row !== null) {
            return $row;
        }

        return self::query()->create([
            'contact_email' => null,
            'primary_phone' => null,
            'secondary_phone' => null,
            'prices_visible' => true,
            'usd_try_rate' => null,
            'usd_try_rate_updated_at' => null,
        ]);
    }

    public static function getContactEmail(): ?string
    {
        $value = self::instance()->contact_email;

        return $value !== null && $value !== '' ? $value : null;
    }

    public static function getPrimaryPhone(): ?string
    {
        $value = self::instance()->primary_phone;

        return $value !== null && $value !== '' ? $value : null;
    }

    public static function getSecondaryPhone(): ?string
    {
        $value = self::instance()->secondary_phone;

        return $value !== null && $value !== '' ? $value : null;
    }

    public static function getPricesVisible(): bool
    {
        return (bool) self::instance()->prices_visible;
    }

    public static function getUsdTryRate(): ?float
    {
        $rate = self::instance()->usd_try_rate;
        if ($rate === null) {
            return null;
        }

        $floatRate = (float) $rate;

        return $floatRate > 0 ? $floatRate : null;
    }
}
