<?php

namespace App\Models;

use App\Support\Traits\HasUlid;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUlid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'locale',
        'timezone',
        'is_platform_admin',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'         => 'datetime',
        'two_factor_confirmed_at'   => 'datetime',
        'last_login_at'             => 'datetime',
        'is_platform_admin'         => 'boolean',
        'is_active'                 => 'boolean',
        'two_factor_enabled'        => 'boolean',
        'preferences'               => 'array',
        'password'                  => 'hashed',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_users')
                    ->withPivot(['role', 'permissions', 'is_active', 'invited_at', 'accepted_at'])
                    ->withTimestamps();
    }

    public function activeProperties(): BelongsToMany
    {
        return $this->properties()->wherePivot('is_active', true);
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class, 'assigned_to');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ─── Role / Permission Helpers ────────────────────────────────────────────

    /**
     * Get the user's role for a specific property.
     */
    public function getRoleForProperty(int|Property $property): ?string
    {
        if ($this->is_platform_admin) {
            return 'platform_admin';
        }

        $propertyId = $property instanceof Property ? $property->id : $property;

        $pivot = $this->properties()
                      ->wherePivot('property_id', $propertyId)
                      ->wherePivot('is_active', true)
                      ->first();

        return $pivot?->pivot->role;
    }

    /**
     * Check if the user has a specific role on a property.
     */
    public function hasRoleOnProperty(string|array $roles, int|Property $property): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        $role = $this->getRoleForProperty($property);

        if (!$role) return false;

        return in_array($role, (array) $roles);
    }

    /**
     * Check if user can access a property at all.
     */
    public function canAccessProperty(int|Property $property): bool
    {
        if ($this->is_platform_admin) return true;

        $propertyId = $property instanceof Property ? $property->id : $property;

        return $this->properties()
                    ->wherePivot('property_id', $propertyId)
                    ->wherePivot('is_active', true)
                    ->exists();
    }

    /**
     * Roles hierarchy for permission checking.
     */
    public static function roleHierarchy(): array
    {
        return [
            'platform_admin' => 100,
            'owner'          => 80,
            'manager'        => 60,
            'accountant'     => 50,
            'receptionist'   => 40,
            'housekeeping'   => 20,
        ];
    }

    /**
     * Check if user's role is at least the given level on property.
     */
    public function hasMinimumRole(string $minimumRole, int|Property $property): bool
    {
        if ($this->is_platform_admin) return true;

        $userRole = $this->getRoleForProperty($property);
        if (!$userRole) return false;

        $hierarchy = static::roleHierarchy();
        $userLevel = $hierarchy[$userRole] ?? 0;
        $requiredLevel = $hierarchy[$minimumRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    // ─── Computed Attributes ──────────────────────────────────────────────────

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }
        // Gravatar fallback
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=200";
    }

    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    public function isDarkMode(): bool
    {
        return $this->getPreference('dark_mode', false);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePlatformAdmins($query)
    {
        return $query->where('is_platform_admin', true);
    }
}
