<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupProof extends Model
{
    /** @use HasFactory<\Database\Factories\TopupProofFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'topup_request_id',
        'file_path',
        'file_original_name',
        'mime_type',
        'size_bytes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topup_request_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function topupRequest(): BelongsTo
    {
        return $this->belongsTo(TopupRequest::class);
    }
}
