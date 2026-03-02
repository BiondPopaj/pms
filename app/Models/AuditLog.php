<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null; // Only created_at

    protected $fillable = [
        'property_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
        'tags',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'tags'       => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeForEvent($query, string|array $events)
    {
        return $query->whereIn('event', (array) $events);
    }

    public function scopeFinancial($query)
    {
        return $query->whereJsonContains('tags', 'financial');
    }

    public function scopeSecurity($query)
    {
        return $query->whereJsonContains('tags', 'security');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
