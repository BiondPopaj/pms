<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => config('app.platform_admin_email', 'admin@pms.local')],
            [
                'name'                => config('app.platform_admin_name', 'Platform Administrator'),
                'password'            => Hash::make(env('PLATFORM_ADMIN_PASSWORD', 'ChangeMeNow!123')),
                'is_platform_admin'   => true,
                'is_active'           => true,
                'email_verified_at'   => now(),
                'timezone'            => 'UTC',
            ]
        );

        $this->command->info("✅ Platform admin: {$admin->email}");
        $this->command->warn('   ⚠️  Change the default password immediately!');
    }
}
