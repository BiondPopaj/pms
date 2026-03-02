<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id',
        'channel',
        'status',
        'credentials',
        'mapping',
        'settings',
        'last_synced_at',
        'last_error_at',
        'last_error_message',
    ];

    protected $casts = [
        'credentials'    => 'encrypted:array',
        'mapping'        => 'array',
        'settings'       => 'array',
        'last_synced_at' => 'datetime',
        'last_error_at'  => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getChannelLabel(): string
    {
        return match($this->channel) {
            'booking_com' => 'Booking.com',
            'expedia'     => 'Expedia',
            'airbnb'      => 'Airbnb',
            default       => ucfirst(str_replace('_', ' ', $this->channel)),
        };
    }
}
