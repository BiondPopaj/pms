<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id',
        'name',
        'code',
        'type',
        'rate',
        'applies_to',
        'is_inclusive',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'rate'         => 'decimal:4',
        'is_inclusive' => 'boolean',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function calculate(float $amount): float
    {
        if ($this->type === 'percentage') {
            return round($amount * ($this->rate / 100), 2);
        }
        return round((float) $this->rate, 2);
    }
}
