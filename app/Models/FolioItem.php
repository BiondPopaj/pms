<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id',
        'folio_id',
        'type',
        'category',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'tax_amount',
        'tax_rate',
        'tax_name',
        'payment_method',
        'payment_reference',
        'charge_date',
        'is_voided',
        'voided_at',
        'voided_by',
        'created_by',
    ];

    protected $casts = [
        'charge_date' => 'date',
        'voided_at'   => 'datetime',
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'amount'      => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'tax_rate'    => 'decimal:4',
        'is_voided'   => 'boolean',
    ];

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function void(int $userId): void
    {
        $this->update([
            'is_voided' => true,
            'voided_at' => now(),
            'voided_by' => $userId,
        ]);
        $this->folio->recalculate();
    }
}
