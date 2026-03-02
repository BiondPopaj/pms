<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Folio extends Model
{
    use BelongsToTenant, SoftDeletes;

    const STATUS_OPEN   = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'property_id',
        'reservation_id',
        'guest_id',
        'folio_number',
        'status',
        'total_charges',
        'total_payments',
        'balance',
        'currency',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'total_charges'  => 'decimal:2',
        'total_payments' => 'decimal:2',
        'balance'        => 'decimal:2',
        'closed_at'      => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(FolioItem::class)->whereIn('type', ['charge', 'tax']);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FolioItem::class)->where('type', 'payment');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function recalculate(): void
    {
        $charges  = $this->items()->whereNotIn('type', ['payment', 'refund'])->where('is_voided', false)->sum('amount');
        $payments = $this->items()->whereIn('type', ['payment', 'refund'])->where('is_voided', false)->sum('amount');

        $this->update([
            'total_charges'  => $charges,
            'total_payments' => $payments,
            'balance'        => $charges - $payments,
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (Folio $folio) {
            if (!$folio->folio_number) {
                $folio->folio_number = 'FOL-' . strtoupper(Str::random(8));
            }
        });
    }
}
