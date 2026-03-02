<?php

namespace App\Models;

use App\Support\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'website',
        'description',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'timezone',
        'currency',
        'locale',
        'logo_path',
        'property_type',
        'star_rating',
        'total_rooms',
        'check_in_time',
        'check_out_time',
        'settings',
        'invoice_settings',
        'subscription_plan_id',
        'subscription_status',
        'subscription_starts_at',
        'subscription_ends_at',
        'trial_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'feature_flags',
        'is_active',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'settings'                => 'array',
        'invoice_settings'        => 'array',
        'feature_flags'           => 'array',
        'latitude'                => 'decimal:7',
        'longitude'               => 'decimal:7',
        'is_active'               => 'boolean',
        'is_verified'             => 'boolean',
        'subscription_starts_at'  => 'datetime',
        'subscription_ends_at'    => 'datetime',
        'trial_ends_at'           => 'datetime',
        'verified_at'             => 'datetime',
    ];

    protected $hidden = [
        'stripe_customer_id',
        'stripe_subscription_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_users')
                    ->withPivot(['role', 'permissions', 'is_active', 'invited_at', 'accepted_at'])
                    ->withTimestamps();
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function bookingSources(): HasMany
    {
        return $this->hasMany(BookingSource::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function taxConfigs(): HasMany
    {
        return $this->hasMany(TaxConfig::class);
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function nightAudits(): HasMany
    {
        return $this->hasMany(NightAudit::class);
    }

    public function channelConnections(): HasMany
    {
        return $this->hasMany(ChannelConnection::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(FeatureFlag::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithActiveSubscription($query)
    {
        return $query->whereIn('subscription_status', ['trial', 'active']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasFeature(string $feature): bool
    {
        $flags = $this->feature_flags ?? [];

        // Check property-specific override first
        if (array_key_exists($feature, $flags)) {
            return (bool) $flags[$feature];
        }

        // Fall back to plan features
        return $this->subscriptionPlan?->hasFeature($feature) ?? false;
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial'
            && $this->trial_ends_at?->isFuture();
    }

    public function isSubscriptionActive(): bool
    {
        return in_array($this->subscription_status, ['trial', 'active'])
            && ($this->subscription_ends_at === null || $this->subscription_ends_at->isFuture());
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) return null;
        return asset('storage/'.$this->logo_path);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }
}
