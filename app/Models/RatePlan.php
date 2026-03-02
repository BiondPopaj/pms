<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatePlan extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    const MEAL_ROOM_ONLY    = 'room_only';
    const MEAL_BED_BREAKFAST= 'bed_breakfast';
    const MEAL_HALF_BOARD   = 'half_board';
    const MEAL_FULL_BOARD   = 'full_board';
    const MEAL_ALL_INCLUSIVE= 'all_inclusive';

    protected $fillable = [
        'property_id',
        'name',
        'code',
        'description',
        'meal_plan',
        'is_refundable',
        'cancellation_days',
        'cancellation_penalty',
        'is_active',
        'is_public',
        'conditions',
    ];

    protected $casts = [
        'is_refundable'       => 'boolean',
        'is_active'           => 'boolean',
        'is_public'           => 'boolean',
        'cancellation_days'   => 'integer',
        'cancellation_penalty'=> 'decimal:2',
        'conditions'          => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'rate_plan_room_types')
                    ->withPivot(['rate', 'extra_adult_rate', 'extra_child_rate', 'is_active'])
                    ->withTimestamps();
    }

    public function roomRates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getRateForRoomType(int $roomTypeId): ?float
    {
        $pivot = $this->roomTypes()
            ->wherePivot('room_type_id', $roomTypeId)
            ->wherePivot('is_active', true)
            ->first();

        return $pivot?->pivot->rate;
    }

    public function getMealPlanLabel(): string
    {
        return match($this->meal_plan) {
            self::MEAL_ROOM_ONLY     => 'Room Only',
            self::MEAL_BED_BREAKFAST => 'Bed & Breakfast',
            self::MEAL_HALF_BOARD    => 'Half Board',
            self::MEAL_FULL_BOARD    => 'Full Board',
            self::MEAL_ALL_INCLUSIVE => 'All Inclusive',
            default                  => ucfirst($this->meal_plan),
        };
    }

    public function getCancellationPolicyLabel(): string
    {
        if (!$this->is_refundable) {
            return 'Non-refundable';
        }
        if ($this->cancellation_days === 0) {
            return 'Free cancellation';
        }
        return "Free cancellation {$this->cancellation_days} days before arrival";
    }
}
