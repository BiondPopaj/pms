<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key'               => 'channel_manager',
                'name'              => 'Channel Manager',
                'description'       => 'OTA channel management (Booking.com, Expedia, Airbnb)',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['professional', 'enterprise'],
            ],
            [
                'key'               => 'revenue_management',
                'name'              => 'Revenue Management',
                'description'       => 'Dynamic pricing and yield management tools',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['enterprise'],
            ],
            [
                'key'               => 'api_access',
                'name'              => 'API Access',
                'description'       => 'External API access for integrations',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['professional', 'enterprise'],
            ],
            [
                'key'               => 'multi_property',
                'name'              => 'Multi-Property Dashboard',
                'description'       => 'Manage multiple properties from one account',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['enterprise'],
            ],
            [
                'key'               => 'ai_assistant',
                'name'              => 'AI Assistant',
                'description'       => 'AI-powered insights and recommendations',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['enterprise'],
            ],
            [
                'key'               => 'white_label',
                'name'              => 'White Label',
                'description'       => 'Custom branding and domain',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['enterprise'],
            ],
            [
                'key'               => 'advanced_reporting',
                'name'              => 'Advanced Reporting',
                'description'       => 'Custom reports, export, scheduled reports',
                'enabled_globally'  => false,
                'enabled_for_plans' => ['professional', 'enterprise'],
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(['key' => $flag['key']], $flag);
        }

        $this->command->info('✅ Feature flags seeded.');
    }
}
