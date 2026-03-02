<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    const HOUSEKEEPING_CLEAN       = 'clean';
    const HOUSEKEEPING_DIRTY       = 'dirty';
    const HOUSEKEEPING_INSPECTING  = 'inspecting';
    const HOUSEKEEPING_OUT_OF_ORDER= 'out_of_order';

    const OCCUPANCY_VACANT    = 'vacant';
    const OCCUPANCY_OCCUPIED  = 'occupied';
    const OCCUPANCY_DEPARTING = 'departing';
    const OCCUPANCY_ARRIVING  = 'arriving';

    protected $fillable = [
        'property_id',
        'room_type_id',
        'room_number',
        'floor',
        'building',
        'view_type',
        'housekeeping_status',
        'occupancy_status',
        'is_smoking',
        'is_accessible',
        'is_active',
        'notes',
        'assigned_housekeeper_id',
        'last_cleaned_at',
    ];

    protected $casts = [
        'is_smoking'      => 'boolean',
        'is_accessible'   => 'boolean',
        'is_active'       => 'boolean',
        'last_cleaned_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function assignedHousekeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_housekeeper_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVacant($query)
    {
        return $query->where('occupancy_status', self::OCCUPANCY_VACANT);
    }

    public function scopeClean($query)
    {
        return $query->where('housekeeping_status', self::HOUSEKEEPING_CLEAN);
    }

    public function scopeReadyForCheckin($query)
    {
        return $query->where('occupancy_status', self::OCCUPANCY_VACANT)
                     ->where('housekeeping_status', self::HOUSEKEEPING_CLEAN);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAvailableFor(\Carbon\Carbon $checkIn, \Carbon\Carbon $checkOut): bool
    {
        if (!$this->is_active || $this->housekeeping_status === self::HOUSEKEEPING_OUT_OF_ORDER) {
            return false;
        }

        return !$this->reservations()
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_PENDING,
            ])
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->exists();
    }

    public function getCurrentReservation(): ?Reservation
    {
        return $this->reservations()
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->latest()
            ->first();
    }

    public static function housekeepingStatuses(): array
    {
        return [
            self::HOUSEKEEPING_CLEAN        => ['label' => 'Clean',        'color' => 'success'],
            self::HOUSEKEEPING_DIRTY        => ['label' => 'Dirty',        'color' => 'danger'],
            self::HOUSEKEEPING_INSPECTING   => ['label' => 'Inspecting',   'color' => 'warning'],
            self::HOUSEKEEPING_OUT_OF_ORDER => ['label' => 'Out of Order', 'color' => 'muted'],
        ];
    }

    public static function occupancyStatuses(): array
    {
        return [
            self::OCCUPANCY_VACANT    => ['label' => 'Vacant',    'color' => 'success'],
            self::OCCUPANCY_OCCUPIED  => ['label' => 'Occupied',  'color' => 'danger'],
            self::OCCUPANCY_DEPARTING => ['label' => 'Departing', 'color' => 'warning'],
            self::OCCUPANCY_ARRIVING  => ['label' => 'Arriving',  'color' => 'info'],
        ];
    }
}
