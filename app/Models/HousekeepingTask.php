<?php

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HousekeepingTask extends Model
{
    use BelongsToTenant, SoftDeletes;

    const STATUS_PENDING    = 'pending';
    const STATUS_IN_PROGRESS= 'in_progress';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_SKIPPED    = 'skipped';

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';
    const PRIORITY_URGENT = 'urgent';

    const TYPE_CHECKOUT_CLEAN = 'checkout_clean';
    const TYPE_STAYOVER_CLEAN = 'stayover_clean';
    const TYPE_DEEP_CLEAN     = 'deep_clean';
    const TYPE_INSPECTION     = 'inspection';
    const TYPE_MAINTENANCE    = 'maintenance';

    protected $fillable = [
        'property_id',
        'room_id',
        'reservation_id',
        'type',
        'status',
        'priority',
        'notes',
        'completion_notes',
        'assigned_to',
        'assigned_by',
        'verified_by',
        'scheduled_date',
        'started_at',
        'completed_at',
        'verified_at',
        'estimated_minutes',
        'actual_minutes',
        'checklist',
    ];

    protected $casts = [
        'scheduled_date'    => 'date',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'verified_at'       => 'datetime',
        'checklist'         => 'array',
        'estimated_minutes' => 'integer',
        'actual_minutes'    => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeToday($query)
    {
        return $query->where('scheduled_date', today());
    }

    public function scopeForHousekeeper($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    // ─── State Transitions ────────────────────────────────────────────────────

    public function start(int $userId): bool
    {
        if ($this->status !== self::STATUS_PENDING) return false;

        $this->update([
            'status'     => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return true;
    }

    public function complete(array $data = []): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) return false;

        $minutes = $this->started_at ? (int) $this->started_at->diffInMinutes(now()) : null;

        $this->update([
            'status'           => self::STATUS_COMPLETED,
            'completed_at'     => now(),
            'actual_minutes'   => $minutes,
            'completion_notes' => $data['notes'] ?? null,
            'checklist'        => $data['checklist'] ?? $this->checklist,
        ]);

        // Update room housekeeping status
        $this->room->update(['housekeeping_status' => Room::HOUSEKEEPING_CLEAN]);

        return true;
    }

    public function verify(int $userId): bool
    {
        if ($this->status !== self::STATUS_COMPLETED) return false;

        $this->update([
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);

        return true;
    }
}
