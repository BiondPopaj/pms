<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id',
        'rate_plan_id',
        'room_type_id',
        'date',
        'rate',
        'availability',
        'closed',
        'closed_to_arrival',
        'closed_to_departure',
        'min_stay',
        'max_stay',
    ];

    protected $casts = [
        'date'                 => 'date',
        'rate'                 => 'decimal:2',
        'closed'               => 'boolean',
        'closed_to_arrival'    => 'boolean',
        'closed_to_departure'  => 'boolean',
        'min_stay'             => 'integer',
        'max_stay'             => 'integer',
        'availability'         => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
