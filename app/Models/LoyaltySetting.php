<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    protected $table = 'loyalty_settings';

    protected $fillable = [
        'rolling_window_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rolling_window_days' => 'integer',
        ];
    }

    public static function getRollingWindowDays(): int
    {
        $row = self::query()->first();

        return $row !== null ? (int) $row->rolling_window_days : (int) config('loyalty.rolling_window_days', 90);
    }

    public static function instance(): self
    {
        $row = self::query()->first();
        if ($row !== null) {
            return $row;
        }

        return self::query()->create(['rolling_window_days' => (int) config('loyalty.rolling_window_days', 90)]);
    }
}
