<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RegistrationCard extends Model
{
    use BelongsToTenant;

    const STATUS_PENDING   = 'pending';
    const STATUS_SENT      = 'sent';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SIGNED    = 'signed';

    protected $fillable = [
        'property_id',
        'reservation_id',
        'guest_id',
        'token',
        'status',
        'guest_data',
        'signature_path',
        'id_document_path',
        'sent_at',
        'completed_at',
        'signed_at',
        'ip_address',
    ];

    protected $casts = [
        'guest_data'   => 'array',
        'sent_at'      => 'datetime',
        'completed_at' => 'datetime',
        'signed_at'    => 'datetime',
    ];

    protected $hidden = ['signature_path', 'id_document_path'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function getSelfFillUrlAttribute(): string
    {
        return url("/registration/{$this->token}");
    }

    protected static function booted(): void
    {
        static::creating(function (RegistrationCard $card) {
            $card->token = Str::random(64);
        });
    }
}
