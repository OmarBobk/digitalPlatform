<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'notification_type',
        'notification_id',
        'trace_id',
        'token_count',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
