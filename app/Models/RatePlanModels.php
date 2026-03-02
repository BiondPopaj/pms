<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RatePlan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'property_id','name','code','description','meal_plan',
        'is_refundable','cancellation_days','cancellation_penalty',
        'is_active','is_public','conditions',
    ];

    protected $casts = [
        'is_refundable'        => 'boolean',
        'is_active'            => 'boolean',
        'is_public'            => 'boolean',
        'cancellation_penalty' => 'decimal:2',
        'conditions'           => 'array',
    ];

    public function property(): BelongsTo     { return $this->belongsTo(Property::class); }
    public function roomTypePrices(): HasMany { return $this->hasMany(RatePlanRoomType::class); }
    public function dailyRates(): HasMany     { return $this->hasMany(RoomRate::class); }
    public function reservations(): HasMany   { return $this->hasMany(Reservation::class); }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopePublic($q)  { return $q->where('is_public', true); }

    public function getMealPlanLabelAttribute(): string
    {
        return [
            'room_only'    => 'Room Only',
            'bed_breakfast'=> 'Bed & Breakfast',
            'half_board'   => 'Half Board',
            'full_board'   => 'Full Board',
            'all_inclusive'=> 'All Inclusive',
        ][$this->meal_plan] ?? $this->meal_plan;
    }
}


class RegistrationCard extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id','reservation_id','guest_id','token','status',
        'guest_data','signature_path','id_document_path',
        'sent_at','completed_at','signed_at','ip_address',
    ];

    protected $casts = [
        'guest_data'   => 'array',
        'sent_at'      => 'datetime',
        'completed_at' => 'datetime',
        'signed_at'    => 'datetime',
    ];

    protected $hidden = ['signature_path','id_document_path'];

    protected static function booted(): void
    {
        static::creating(fn ($r) => $r->token ??= Str::random(64));
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
}
