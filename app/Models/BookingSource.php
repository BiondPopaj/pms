<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingSource extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'code',
        'type',
        'commission_percent',
        'color',
        'is_active',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'is_active'          => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'direct'    => 'Direct',
            'ota'       => 'OTA',
            'gds'       => 'GDS',
            'corporate' => 'Corporate',
            'group'     => 'Group',
            default     => ucfirst($this->type),
        };
    }
}
