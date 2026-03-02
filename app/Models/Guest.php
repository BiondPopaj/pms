<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'nationality',
        'language',
        'date_of_birth',
        'gender',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'company_name',
        'vat_number',
        'passport_number',
        'id_type',
        'id_number',
        'id_expiry',
        'id_document_path',
        'total_revenue',
        'total_stays',
        'vip_status',
        'notes',
        'internal_notes',
        'is_blacklisted',
        'blacklist_reason',
        'preferences',
    ];

    protected $casts = [
        'date_of_birth'  => 'date',
        'id_expiry'      => 'date',
        'total_revenue'  => 'decimal:2',
        'total_stays'    => 'integer',
        'is_blacklisted' => 'boolean',
        'preferences'    => 'array',
    ];

    protected $hidden = [
        'passport_number',
        'id_number',
        'id_document_path',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function additionalReservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_guests')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function registrationCards(): HasMany
    {
        return $this->hasMany(RegistrationCard::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'ilike', "%{$term}%")
              ->orWhere('last_name', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('phone', 'ilike', "%{$term}%")
              ->orWhereRaw("concat(first_name, ' ', last_name) ilike ?", ["%{$term}%"]);
        });
    }

    public function scopeNotBlacklisted($query)
    {
        return $query->where('is_blacklisted', false);
    }

    // ─── Computed Attributes ──────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function updateStats(): void
    {
        $this->update([
            'total_stays'   => $this->reservations()
                ->where('status', Reservation::STATUS_CHECKED_OUT)
                ->count(),
            'total_revenue' => $this->reservations()
                ->where('status', Reservation::STATUS_CHECKED_OUT)
                ->sum('total_amount'),
        ]);
    }

    public function getVipLabel(): ?string
    {
        return match($this->vip_status) {
            'silver'   => 'Silver',
            'gold'     => 'Gold',
            'platinum' => 'Platinum',
            default    => null,
        };
    }
}
