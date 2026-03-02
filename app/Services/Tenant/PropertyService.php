<?php

namespace App\Services\Tenant;

use App\Models\BookingSource;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use App\Models\TaxConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PropertyService
{
    /**
     * Create a new property with owner user, default booking sources, and tax config.
     */
    public function createProperty(array $data): Property
    {
        return DB::transaction(function () use ($data) {

            // ── 1. Create the Property ─────────────────────────────────────
            $plan = isset($data['subscription_plan_id'])
                ? SubscriptionPlan::find($data['subscription_plan_id'])
                : SubscriptionPlan::where('slug', 'trial')->first();

            $property = Property::create([
                'name'                => $data['name'],
                'slug'                => $this->generateSlug($data['name']),
                'email'               => $data['email'],
                'phone'               => $data['phone'] ?? null,
                'country'             => $data['country'],
                'city'                => $data['city'] ?? null,
                'timezone'            => $data['timezone'],
                'currency'            => $data['currency'],
                'property_type'       => $data['property_type'],
                'subscription_plan_id'=> $plan?->id,
                'subscription_status' => 'trial',
                'trial_ends_at'       => now()->addDays(14),
                'check_in_time'       => '14:00',
                'check_out_time'      => '11:00',
                'settings'            => $this->defaultSettings(),
                'is_active'           => true,
            ]);

            // ── 2. Create or find owner user ───────────────────────────────
            $owner = User::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'name'     => $data['owner_name'],
                    'password' => Hash::make($data['owner_password']),
                    'is_active'=> true,
                    'timezone' => $data['timezone'],
                    'email_verified_at' => now(),
                ]
            );

            // ── 3. Attach owner to property ────────────────────────────────
            $property->users()->attach($owner->id, [
                'role'        => 'owner',
                'is_active'   => true,
                'accepted_at' => now(),
            ]);

            // ── 4. Seed default booking sources ────────────────────────────
            $this->seedDefaultBookingSources($property);

            // ── 5. Seed default tax configuration ──────────────────────────
            $this->seedDefaultTaxConfig($property);

            return $property->load(['subscriptionPlan']);
        });
    }

    /**
     * Default settings for new properties.
     */
    private function defaultSettings(): array
    {
        return [
            'require_payment_on_booking' => false,
            'auto_confirm_reservations'  => false,
            'send_confirmation_email'    => true,
            'send_pre_arrival_email'     => true,
            'pre_arrival_days'           => 2,
            'send_post_stay_email'       => true,
            'post_stay_days'             => 1,
            'overbooking_allowed'        => false,
            'overbooking_limit'          => 0,
            'min_stay'                   => 1,
            'max_stay'                   => 30,
            'registration_card_required' => true,
            'digital_signature_required' => false,
            'housekeeping_auto_assign'   => false,
            'night_audit_auto_run'       => false,
            'night_audit_time'           => '23:59',
            'invoice_prefix'             => 'INV',
            'folio_prefix'               => 'FOL',
            'reservation_prefix'         => 'RES',
        ];
    }

    /**
     * Seed default booking sources for a new property.
     */
    private function seedDefaultBookingSources(Property $property): void
    {
        $sources = [
            ['name' => 'Direct',       'code' => 'DIRECT',   'type' => 'direct',    'commission_percent' => 0,    'color' => '#6171f6'],
            ['name' => 'Walk-in',      'code' => 'WALKIN',   'type' => 'direct',    'commission_percent' => 0,    'color' => '#22c55e'],
            ['name' => 'Phone',        'code' => 'PHONE',    'type' => 'direct',    'commission_percent' => 0,    'color' => '#3b82f6'],
            ['name' => 'Booking.com',  'code' => 'BOOKINGCOM','type' => 'ota',      'commission_percent' => 15.0, 'color' => '#003580'],
            ['name' => 'Expedia',      'code' => 'EXPEDIA',  'type' => 'ota',       'commission_percent' => 18.0, 'color' => '#FFC72C'],
            ['name' => 'Airbnb',       'code' => 'AIRBNB',   'type' => 'ota',       'commission_percent' => 3.0,  'color' => '#FF5A5F'],
            ['name' => 'Corporate',    'code' => 'CORPORATE','type' => 'corporate', 'commission_percent' => 0,    'color' => '#64748b'],
            ['name' => 'Group',        'code' => 'GROUP',    'type' => 'group',     'commission_percent' => 0,    'color' => '#8b5cf6'],
        ];

        foreach ($sources as $source) {
            $property->bookingSources()->create($source);
        }
    }

    /**
     * Seed default tax configuration.
     */
    private function seedDefaultTaxConfig(Property $property): void
    {
        // Default VAT - property can adjust rate
        $property->taxConfigs()->create([
            'name'        => 'VAT',
            'code'        => 'VAT',
            'type'        => 'percentage',
            'rate'        => 0.20, // 20% - adjust per country
            'applies_to'  => 'all',
            'is_inclusive'=> false,
            'is_active'   => false, // Inactive by default, owner configures
            'sort_order'  => 1,
        ]);

        $property->taxConfigs()->create([
            'name'        => 'City Tax',
            'code'        => 'CITY',
            'type'        => 'fixed',
            'rate'        => 0,
            'applies_to'  => 'room',
            'is_inclusive'=> false,
            'is_active'   => false,
            'sort_order'  => 2,
        ]);
    }

    /**
     * Generate a unique slug for the property.
     */
    private function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Property::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter++;
        }

        return $slug;
    }
}
