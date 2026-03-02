<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToTenant, SoftDeletes;

    const STATUS_DRAFT  = 'draft';
    const STATUS_ISSUED = 'issued';
    const STATUS_PAID   = 'paid';
    const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'property_id',
        'folio_id',
        'guest_id',
        'invoice_number',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'currency',
        'issue_date',
        'due_date',
        'billing_address',
        'line_items',
        'pdf_path',
        'issued_at',
        'paid_at',
        'issued_by',
    ];

    protected $casts = [
        'issue_date'      => 'date',
        'due_date'        => 'date',
        'issued_at'       => 'datetime',
        'paid_at'         => 'datetime',
        'billing_address' => 'array',
        'line_items'      => 'array',
        'subtotal'        => 'decimal:2',
        'tax_total'       => 'decimal:2',
        'discount_total'  => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeIssued($query)
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function issue(int $userId): void
    {
        $this->update([
            'status'    => self::STATUS_ISSUED,
            'issued_at' => now(),
            'issued_by' => $userId,
        ]);
    }

    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_path) return null;
        return asset('storage/' . $this->pdf_path);
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (!$invoice->invoice_number) {
                $prefix = 'INV';
                $invoice->invoice_number = $prefix . '-' . date('Ym') . '-' . strtoupper(Str::random(6));
            }
        });
    }
}
