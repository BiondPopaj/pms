<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name','slug','description','max_properties','max_rooms','max_users',
        'price_monthly','price_yearly','currency','features','is_active','sort_order',
        'stripe_price_monthly_id','stripe_price_yearly_id',
    ];

    protected $casts = [
        'features'      => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly'  => 'decimal:2',
        'is_active'     => 'boolean',
        'max_properties'=> 'integer',
        'max_rooms'     => 'integer',
        'max_users'     => 'integer',
    ];

    protected $hidden = ['stripe_price_monthly_id','stripe_price_yearly_id'];

    public function properties(): HasMany { return $this->hasMany(Property::class); }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function isUnlimited(string $limit): bool
    {
        return ($this->{$limit} ?? 0) === -1;
    }

    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('sort_order'); }
}


// ─── HousekeepingTask.php ─────────────────────────────────────────────────────

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HousekeepingTask extends Model
{
    use BelongsToTenant, SoftDeletes;

    const TYPE_CHECKOUT_CLEAN = 'checkout_clean';
    const TYPE_STAYOVER_CLEAN = 'stayover_clean';
    const TYPE_DEEP_CLEAN     = 'deep_clean';
    const TYPE_INSPECTION     = 'inspection';
    const TYPE_MAINTENANCE    = 'maintenance';

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_SKIPPED     = 'skipped';

    protected $fillable = [
        'property_id','room_id','reservation_id','type','status','priority','notes',
        'completion_notes','assigned_to','assigned_by','verified_by','scheduled_date',
        'started_at','completed_at','verified_at','estimated_minutes','actual_minutes','checklist',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'verified_at'    => 'datetime',
        'checklist'      => 'array',
    ];

    public function property(): BelongsTo    { return $this->belongsTo(Property::class); }
    public function room(): BelongsTo        { return $this->belongsTo(Room::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function assignedTo(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function assignedBy(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_by'); }
    public function verifiedBy(): BelongsTo  { return $this->belongsTo(User::class, 'verified_by'); }

    public function scopePending($q)     { return $q->where('status', self::STATUS_PENDING); }
    public function scopeInProgress($q)  { return $q->where('status', self::STATUS_IN_PROGRESS); }
    public function scopeForToday($q)    { return $q->where('scheduled_date', today()); }
    public function scopeForUser($q, $userId) { return $q->where('assigned_to', $userId); }
}


// ─── BookingSource.php ────────────────────────────────────────────────────────

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingSource extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id','name','code','type','commission_percent','color','is_active',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'is_active'          => 'boolean',
    ];

    public function property(): BelongsTo    { return $this->belongsTo(Property::class); }
    public function reservations(): HasMany  { return $this->hasMany(Reservation::class); }
    public function scopeActive($q)          { return $q->where('is_active', true); }
}


// ─── FolioItem.php ────────────────────────────────────────────────────────────

namespace App\Models;

use App\Support\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'property_id','folio_id','type','category','description','quantity',
        'unit_price','amount','tax_amount','tax_rate','tax_name',
        'payment_method','payment_reference','charge_date',
        'is_voided','voided_at','voided_by','created_by',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'amount'      => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'tax_rate'    => 'decimal:4',
        'charge_date' => 'date',
        'is_voided'   => 'boolean',
        'voided_at'   => 'datetime',
    ];

    public function folio(): BelongsTo     { return $this->belongsTo(Folio::class); }
    public function property(): BelongsTo  { return $this->belongsTo(Property::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function voidedBy(): BelongsTo  { return $this->belongsTo(User::class, 'voided_by'); }

    public function scopeCharges($q)  { return $q->where('type', 'charge')->where('is_voided', false); }
    public function scopePayments($q) { return $q->where('type', 'payment')->where('is_voided', false); }
    public function scopeActive($q)   { return $q->where('is_voided', false); }
}
