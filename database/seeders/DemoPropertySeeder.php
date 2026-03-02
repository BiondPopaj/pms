<?php

namespace Database\Seeders;

use App\Models\BookingSource;
use App\Models\Guest;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Tenant\PropertyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoPropertySeeder extends Seeder
{
    public function __construct(private readonly PropertyService $propertyService) {}

    public function run(): void
    {
        // ── Create Demo Property ────────────────────────────────────────────
        $property = $this->propertyService->createProperty([
            'name'          => 'Grand Azure Hotel',
            'email'         => 'info@grandazure-demo.com',
            'phone'         => '+1 555 000 1234',
            'country'       => 'US',
            'city'          => 'Miami',
            'state'         => 'FL',
            'postal_code'   => '33101',
            'address_line1' => '1 Ocean Drive',
            'timezone'      => 'America/New_York',
            'currency'      => 'USD',
            'property_type' => 'hotel',
            'star_rating'   => 4,
            'owner_name'    => 'Hotel Owner',
            'owner_email'   => 'owner@grandazure-demo.com',
            'owner_password'=> 'DemoOwner123!',
        ]);

        $plan = SubscriptionPlan::where('slug', 'professional')->first();
        $property->update([
            'subscription_plan_id' => $plan->id,
            'subscription_status'  => 'active',
            'subscription_ends_at' => now()->addYear(),
            'star_rating'          => 4,
        ]);

        // ── Staff Users ─────────────────────────────────────────────────────
        $staff = [
            ['name' => 'Maria Manager',      'email' => 'manager@grandazure-demo.com',      'role' => 'manager'],
            ['name' => 'Rosa Receptionist',  'email' => 'receptionist@grandazure-demo.com', 'role' => 'receptionist'],
            ['name' => 'Henry Housekeeper',  'email' => 'housekeeping@grandazure-demo.com', 'role' => 'housekeeping'],
            ['name' => 'Amy Accountant',     'email' => 'accountant@grandazure-demo.com',   'role' => 'accountant'],
        ];

        foreach ($staff as $member) {
            $user = User::updateOrCreate(
                ['email' => $member['email']],
                [
                    'name'             => $member['name'],
                    'password'         => Hash::make('Demo123!'),
                    'is_active'        => true,
                    'email_verified_at'=> now(),
                    'timezone'         => 'America/New_York',
                ]
            );

            if (!$property->users()->where('user_id', $user->id)->exists()) {
                $property->users()->attach($user->id, [
                    'role'        => $member['role'],
                    'is_active'   => true,
                    'accepted_at' => now(),
                ]);
            }
        }

        // ── Room Types ─────────────────────────────────────────────────────
        $roomTypes = [
            [
                'name'            => 'Standard King',
                'code'            => 'STK',
                'description'     => 'Comfortable standard room with king bed and city view.',
                'base_occupancy'  => 2,
                'max_occupancy'   => 2,
                'max_adults'      => 2,
                'max_children'    => 0,
                'bed_type'        => 'king',
                'size_sqm'        => 28,
                'amenities'       => ['wifi', 'tv', 'air_conditioning', 'private_bathroom', 'safe', 'minibar'],
                'base_rate'       => 129.00,
                'is_active'       => true,
                'sort_order'      => 1,
            ],
            [
                'name'            => 'Deluxe Twin',
                'code'            => 'DXT',
                'description'     => 'Spacious twin room ideal for two guests or families.',
                'base_occupancy'  => 2,
                'max_occupancy'   => 3,
                'max_adults'      => 2,
                'max_children'    => 1,
                'bed_type'        => 'twin',
                'size_sqm'        => 32,
                'amenities'       => ['wifi', 'tv', 'air_conditioning', 'private_bathroom', 'safe', 'minibar', 'balcony'],
                'base_rate'       => 149.00,
                'is_active'       => true,
                'sort_order'      => 2,
            ],
            [
                'name'            => 'Deluxe King Ocean View',
                'code'            => 'DKO',
                'description'     => 'Premium room with stunning ocean panoramic views.',
                'base_occupancy'  => 2,
                'max_occupancy'   => 3,
                'max_adults'      => 2,
                'max_children'    => 1,
                'bed_type'        => 'king',
                'size_sqm'        => 38,
                'amenities'       => ['wifi', 'tv', 'air_conditioning', 'private_bathroom', 'safe', 'minibar', 'balcony', 'ocean_view', 'bathtub'],
                'base_rate'       => 199.00,
                'is_active'       => true,
                'sort_order'      => 3,
            ],
            [
                'name'            => 'Junior Suite',
                'code'            => 'JST',
                'description'     => 'Elegant suite with separate living area and premium amenities.',
                'base_occupancy'  => 2,
                'max_occupancy'   => 4,
                'max_adults'      => 3,
                'max_children'    => 2,
                'bed_type'        => 'king',
                'size_sqm'        => 55,
                'amenities'       => ['wifi', 'tv', 'air_conditioning', 'private_bathroom', 'safe', 'minibar', 'balcony', 'ocean_view', 'bathtub', 'living_room', 'nespresso'],
                'base_rate'       => 299.00,
                'is_active'       => true,
                'sort_order'      => 4,
            ],
            [
                'name'            => 'Presidential Suite',
                'code'            => 'PST',
                'description'     => 'The pinnacle of luxury with butler service and private terrace.',
                'base_occupancy'  => 2,
                'max_occupancy'   => 6,
                'max_adults'      => 4,
                'max_children'    => 2,
                'bed_type'        => 'king',
                'size_sqm'        => 120,
                'amenities'       => ['wifi', 'tv', 'air_conditioning', 'private_bathroom', 'safe', 'minibar', 'balcony', 'ocean_view', 'bathtub', 'living_room', 'nespresso', 'butler', 'private_terrace', 'jacuzzi', 'kitchen'],
                'base_rate'       => 699.00,
                'is_active'       => true,
                'sort_order'      => 5,
            ],
        ];

        $createdRoomTypes = [];
        foreach ($roomTypes as $rtData) {
            $rt = $property->roomTypes()->create($rtData);
            $createdRoomTypes[$rt->code] = $rt;
        }

        // ── Physical Rooms ──────────────────────────────────────────────────
        $floors = [1, 2, 3, 4, 5, 6, 7, 8];
        $roomAssignments = [
            'STK' => ['101','102','201','202','301','302','401','402','501','502'],
            'DXT' => ['103','104','203','204','303','304','403','404','503','504'],
            'DKO' => ['105','106','205','206','305','306','405','406','505','506'],
            'JST' => ['601','602','701','702'],
            'PST' => ['801'],
        ];

        foreach ($roomAssignments as $typeCode => $roomNumbers) {
            $roomType = $createdRoomTypes[$typeCode];
            foreach ($roomNumbers as $roomNumber) {
                $floor = substr($roomNumber, 0, strlen($roomNumber) - 2);
                $property->rooms()->create([
                    'room_type_id'       => $roomType->id,
                    'room_number'        => $roomNumber,
                    'floor'              => $floor,
                    'housekeeping_status'=> 'clean',
                    'occupancy_status'   => 'vacant',
                    'is_smoking'         => false,
                    'is_active'          => true,
                ]);
            }
        }

        // Update total_rooms count
        $property->update(['total_rooms' => $property->rooms()->count()]);

        // ── Rate Plans ──────────────────────────────────────────────────────
        $ratePlans = [
            [
                'name'                 => 'Best Available Rate',
                'code'                 => 'BAR',
                'description'          => 'Our best publicly available rate.',
                'meal_plan'            => 'room_only',
                'is_refundable'        => true,
                'cancellation_days'    => 1,
                'cancellation_penalty' => 0,
                'is_active'            => true,
                'is_public'            => true,
            ],
            [
                'name'                 => 'Non-Refundable',
                'code'                 => 'NRF',
                'description'          => 'Discounted rate with no cancellation.',
                'meal_plan'            => 'room_only',
                'is_refundable'        => false,
                'cancellation_days'    => 0,
                'cancellation_penalty' => 100,
                'is_active'            => true,
                'is_public'            => true,
            ],
            [
                'name'                 => 'Bed & Breakfast',
                'code'                 => 'BB',
                'description'          => 'Room rate including daily breakfast.',
                'meal_plan'            => 'bed_breakfast',
                'is_refundable'        => true,
                'cancellation_days'    => 2,
                'cancellation_penalty' => 50,
                'is_active'            => true,
                'is_public'            => true,
            ],
            [
                'name'                 => 'Corporate Rate',
                'code'                 => 'CORP',
                'description'          => 'Negotiated rate for corporate accounts.',
                'meal_plan'            => 'room_only',
                'is_refundable'        => true,
                'cancellation_days'    => 1,
                'cancellation_penalty' => 0,
                'is_active'            => true,
                'is_public'            => false,
            ],
        ];

        foreach ($ratePlans as $rpData) {
            $property->ratePlans()->create($rpData);
        }

        // ── Demo Guests ─────────────────────────────────────────────────────
        $guests = [
            ['first_name' => 'James',   'last_name' => 'Morrison',  'email' => 'james.morrison@email.com',  'nationality' => 'US', 'vip_status' => 'gold'],
            ['first_name' => 'Sophie',  'last_name' => 'Laurent',   'email' => 'sophie.laurent@email.com',  'nationality' => 'FR', 'vip_status' => null],
            ['first_name' => 'Carlos',  'last_name' => 'Rodriguez', 'email' => 'carlos.r@email.com',        'nationality' => 'ES', 'vip_status' => null],
            ['first_name' => 'Emily',   'last_name' => 'Chen',      'email' => 'emily.chen@email.com',      'nationality' => 'CN', 'vip_status' => 'platinum'],
            ['first_name' => 'Michael', 'last_name' => 'Brown',     'email' => 'mbrown@corp.com',           'nationality' => 'GB', 'vip_status' => 'silver'],
        ];

        foreach ($guests as $guestData) {
            $property->guests()->create(array_merge($guestData, [
                'language' => 'en',
                'total_stays' => rand(1, 15),
                'total_revenue' => rand(500, 15000),
            ]));
        }

        $this->command->info('✅ Demo property "Grand Azure Hotel" seeded.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Owner',       'owner@grandazure-demo.com',       'DemoOwner123!'],
                ['Manager',     'manager@grandazure-demo.com',     'Demo123!'],
                ['Receptionist','receptionist@grandazure-demo.com','Demo123!'],
                ['Housekeeping','housekeeping@grandazure-demo.com','Demo123!'],
                ['Accountant',  'accountant@grandazure-demo.com',  'Demo123!'],
            ]
        );
    }
}
