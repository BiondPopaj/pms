<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'name',
        'code',
        'description',
        'base_occupancy',
        'max_occupancy',
        'max_adults',
        'max_children',
        'bed_type',
        'size_sqm',
        'amenities',
        'photos',
        'base_rate',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amenities'      => 'array',
        'photos'         => 'array',
        'base_rate'      => 'decimal:2',
        'size_sqm'       => 'decimal:2',
        'is_active'      => 'boolean',
        'base_occupancy' => 'integer',
        'max_occupancy'  => 'integer',
        'max_adults'     => 'integer',
        'max_children'   => 'integer',
        'sort_order'     => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function ratePlans(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(RatePlan::class, 'rate_plan_room_types')
                    ->withPivot(['rate', 'extra_adult_rate', 'extra_child_rate', 'is_active'])
                    ->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function roomRates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getAvailableRoomsCount(\Carbon\Carbon $checkIn, \Carbon\Carbon $checkOut): int
    {
        $totalRooms = $this->rooms()->where('is_active', true)->count();

        $occupied = Reservation::where('room_type_id', $this->id)
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_PENDING,
            ])
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->count();

        return max(0, $totalRooms - $occupied);
    }
}
