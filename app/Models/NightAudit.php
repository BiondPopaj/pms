<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NightAudit extends Model
{
    use BelongsToTenant;

    const STATUS_PENDING    = 'pending';
    const STATUS_IN_PROGRESS= 'in_progress';
    const STATUS_COMPLETED  = 'completed';

    protected $fillable = [
        'property_id',
        'audit_date',
        'status',
        'total_revenue',
        'room_revenue',
        'other_revenue',
        'total_tax',
        'rooms_occupied',
        'rooms_available',
        'occupancy_rate',
        'adr',
        'revpar',
        'arrivals',
        'departures',
        'no_shows',
        'summary',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'audit_date'      => 'date',
        'total_revenue'   => 'decimal:2',
        'room_revenue'    => 'decimal:2',
        'other_revenue'   => 'decimal:2',
        'total_tax'       => 'decimal:2',
        'rooms_occupied'  => 'integer',
        'rooms_available' => 'integer',
        'occupancy_rate'  => 'decimal:2',
        'adr'             => 'decimal:2',
        'revpar'          => 'decimal:2',
        'arrivals'        => 'integer',
        'departures'      => 'integer',
        'no_shows'        => 'integer',
        'summary'         => 'array',
        'completed_at'    => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
