<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'            => 'Trial',
                'slug'            => 'trial',
                'description'     => '14-day free trial with full access.',
                'max_properties'  => 1,
                'max_rooms'       => 10,
                'max_users'       => 3,
                'price_monthly'   => 0,
                'price_yearly'    => 0,
                'currency'        => 'USD',
                'is_active'       => true,
                'sort_order'      => 0,
                'features'        => [
                    'reservations'        => true,
                    'front_desk'          => true,
                    'housekeeping'        => true,
                    'billing'             => true,
                    'reports'             => false,
                    'channel_manager'     => false,
                    'revenue_management'  => false,
                    'api_access'          => false,
                    'multi_property'      => false,
                    'white_label'         => false,
                ],
            ],
            [
                'name'            => 'Starter',
                'slug'            => 'starter',
                'description'     => 'Perfect for small hotels and B&Bs up to 25 rooms.',
                'max_properties'  => 1,
                'max_rooms'       => 25,
                'max_users'       => 5,
                'price_monthly'   => 79,
                'price_yearly'    => 790,
                'currency'        => 'USD',
                'is_active'       => true,
                'sort_order'      => 1,
                'features'        => [
                    'reservations'        => true,
                    'front_desk'          => true,
                    'housekeeping'        => true,
                    'billing'             => true,
                    'reports'             => true,
                    'channel_manager'     => false,
                    'revenue_management'  => false,
                    'api_access'          => false,
                    'multi_property'      => false,
                    'white_label'         => false,
                ],
            ],
            [
                'name'            => 'Professional',
                'slug'            => 'professional',
                'description'     => 'For growing properties up to 100 rooms with channel management.',
                'max_properties'  => 1,
                'max_rooms'       => 100,
                'max_users'       => 20,
                'price_monthly'   => 199,
                'price_yearly'    => 1990,
                'currency'        => 'USD',
                'is_active'       => true,
                'sort_order'      => 2,
                'features'        => [
                    'reservations'        => true,
                    'front_desk'          => true,
                    'housekeeping'        => true,
                    'billing'             => true,
                    'reports'             => true,
                    'channel_manager'     => true,
                    'revenue_management'  => false,
                    'api_access'          => true,
                    'multi_property'      => false,
                    'white_label'         => false,
                ],
            ],
            [
                'name'            => 'Enterprise',
                'slug'            => 'enterprise',
                'description'     => 'Unlimited properties, rooms, and users. Full feature set.',
                'max_properties'  => -1,   // unlimited
                'max_rooms'       => -1,   // unlimited
                'max_users'       => -1,   // unlimited
                'price_monthly'   => 499,
                'price_yearly'    => 4990,
                'currency'        => 'USD',
                'is_active'       => true,
                'sort_order'      => 3,
                'features'        => [
                    'reservations'        => true,
                    'front_desk'          => true,
                    'housekeeping'        => true,
                    'billing'             => true,
                    'reports'             => true,
                    'channel_manager'     => true,
                    'revenue_management'  => true,
                    'api_access'          => true,
                    'multi_property'      => true,
                    'white_label'         => true,
                    'dedicated_support'   => true,
                    'sla'                 => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('✅ Subscription plans seeded.');
    }
}
