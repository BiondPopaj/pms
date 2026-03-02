<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationStatusHistory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'reservation_id',
        'property_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
