<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled_globally',
        'enabled_for_plans',
        'enabled_for_properties',
    ];

    protected $casts = [
        'enabled_globally'        => 'boolean',
        'enabled_for_plans'       => 'array',
        'enabled_for_properties'  => 'array',
    ];

    public function isEnabledFor(Property $property): bool
    {
        if ($this->enabled_globally) return true;

        // Check property-specific override
        $propertyOverrides = $this->enabled_for_properties ?? [];
        if (in_array($property->id, $propertyOverrides)) return true;

        // Check plan
        $plan = $property->subscriptionPlan;
        if ($plan && in_array($plan->slug, $this->enabled_for_plans ?? [])) return true;

        return false;
    }

    public function toggle(): void
    {
        $this->update(['enabled_globally' => !$this->enabled_globally]);
    }

    public function scopeActive($query)
    {
        return $query->where('enabled_globally', true);
    }
}
