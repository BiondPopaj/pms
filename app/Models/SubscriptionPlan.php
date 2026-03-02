<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_properties',
        'max_rooms',
        'max_users',
        'price_monthly',
        'price_yearly',
        'currency',
        'features',
        'is_active',
        'sort_order',
        'stripe_price_monthly_id',
        'stripe_price_yearly_id',
    ];

    protected $casts = [
        'features'        => 'array',
        'price_monthly'   => 'decimal:2',
        'price_yearly'    => 'decimal:2',
        'is_active'       => 'boolean',
        'max_properties'  => 'integer',
        'max_rooms'       => 'integer',
        'max_users'       => 'integer',
        'sort_order'      => 'integer',
    ];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getSavingsPercentAttribute(): int
    {
        if (!$this->price_monthly || !$this->price_yearly) return 0;
        $annualMonthly = $this->price_monthly * 12;
        return (int) round((($annualMonthly - $this->price_yearly) / $annualMonthly) * 100);
    }
}
