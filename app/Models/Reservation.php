<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // Reservation lifecycle states
    const STATUS_PENDING     = 'pending';
    const STATUS_CONFIRMED   = 'confirmed';
    const STATUS_CHECKED_IN  = 'checked_in';
    const STATUS_CHECKED_OUT = 'checked_out';
    const STATUS_NO_SHOW     = 'no_show';
    const STATUS_CANCELLED   = 'cancelled';

    const PAYMENT_UNPAID    = 'unpaid';
    const PAYMENT_PARTIAL   = 'partial';
    const PAYMENT_PAID      = 'paid';
    const PAYMENT_REFUNDED  = 'refunded';

    protected $fillable = [
        'property_id',
        'reservation_number',
        'status',
        'guest_id',
        'room_type_id',
        'room_id',
        'rate_plan_id',
        'booking_source_id',
        'check_in_date',
        'check_out_date',
        'nights',
        'adults',
        'children',
        'infants',
        'room_rate',
        'total_room',
        'total_extras',
        'total_tax',
        'total_discount',
        'total_amount',
        'total_paid',
        'balance_due',
        'currency',
        'ota_confirmation_number',
        'ota_commission',
        'payment_status',
        'special_requests',
        'internal_notes',
        'confirmed_at',
        'checked_in_at',
        'checked_out_at',
        'cancelled_at',
        'cancellation_reason',
        'cancellation_fee',
        'created_by',
        'checked_in_by',
        'checked_out_by',
        'cancelled_by',
        'is_group_booking',
        'group_id',
        'extras',
        'metadata',
    ];

    protected $casts = [
        'check_in_date'   => 'date',
        'check_out_date'  => 'date',
        'confirmed_at'    => 'datetime',
        'checked_in_at'   => 'datetime',
        'checked_out_at'  => 'datetime',
        'cancelled_at'    => 'datetime',
        'room_rate'       => 'decimal:2',
        'total_room'      => 'decimal:2',
        'total_extras'    => 'decimal:2',
        'total_tax'       => 'decimal:2',
        'total_discount'  => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'total_paid'      => 'decimal:2',
        'balance_due'     => 'decimal:2',
        'ota_commission'  => 'decimal:2',
        'cancellation_fee'=> 'decimal:2',
        'is_group_booking'=> 'boolean',
        'extras'          => 'array',
        'metadata'        => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function bookingSource(): BelongsTo
    {
        return $this->belongsTo(BookingSource::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function additionalGuests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'reservation_guests')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class)->latest();
    }

    public function folio(): HasOne
    {
        return $this->hasOne(Folio::class);
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function registrationCard(): HasOne
    {
        return $this->hasOne(RegistrationCard::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeStatus($query, string|array $status)
    {
        return $query->whereIn('status', (array) $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
        ]);
    }

    public function scopeArriving($query, $date = null)
    {
        $date = $date ?? today();
        return $query->where('check_in_date', $date)
                     ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeDeparting($query, $date = null)
    {
        $date = $date ?? today();
        return $query->where('check_out_date', $date)
                     ->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeInHouse($query)
    {
        return $query->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->where('check_in_date', '<', $to)
                     ->where('check_out_date', '>', $from);
    }

    // ─── State Machine Helpers ────────────────────────────────────────────────

    public function canTransitionTo(string $status): bool
    {
        $allowed = [
            self::STATUS_PENDING     => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED   => [self::STATUS_CHECKED_IN, self::STATUS_NO_SHOW, self::STATUS_CANCELLED],
            self::STATUS_CHECKED_IN  => [self::STATUS_CHECKED_OUT],
            self::STATUS_CHECKED_OUT => [],
            self::STATUS_NO_SHOW     => [self::STATUS_CONFIRMED],
            self::STATUS_CANCELLED   => [],
        ];

        return in_array($status, $allowed[$this->status] ?? []);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function getNightsAttribute($value): int
    {
        if ($value) return $value;
        if ($this->check_in_date && $this->check_out_date) {
            return $this->check_in_date->diffInDays($this->check_out_date);
        }
        return 0;
    }

    // ─── Unique Reservation Number ────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            if (!$reservation->reservation_number) {
                $reservation->reservation_number = static::generateReservationNumber();
            }
        });
    }

    public static function generateReservationNumber(): string
    {
        do {
            $number = 'RES-'.strtoupper(Str::random(8));
        } while (static::where('reservation_number', $number)->exists());

        return $number;
    }

    // ─── Status Labels ────────────────────────────────────────────────────────

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING     => ['label' => 'Pending',     'color' => 'warning'],
            self::STATUS_CONFIRMED   => ['label' => 'Confirmed',   'color' => 'info'],
            self::STATUS_CHECKED_IN  => ['label' => 'Checked In',  'color' => 'success'],
            self::STATUS_CHECKED_OUT => ['label' => 'Checked Out', 'color' => 'neutral'],
            self::STATUS_NO_SHOW     => ['label' => 'No Show',     'color' => 'danger'],
            self::STATUS_CANCELLED   => ['label' => 'Cancelled',   'color' => 'muted'],
        ];
    }
}
