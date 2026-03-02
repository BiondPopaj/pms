<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Hotel';
        return [
            'name'                => $name,
            'slug'                => Str::slug($name).'-'.Str::random(4),
            'email'               => fake()->unique()->companyEmail(),
            'phone'               => fake()->phoneNumber(),
            'city'                => fake()->city(),
            'country'             => fake()->countryCode(),
            'timezone'            => 'UTC',
            'currency'            => 'USD',
            'locale'              => 'en_US',
            'property_type'       => 'hotel',
            'check_in_time'       => '14:00',
            'check_out_time'      => '11:00',
            'total_rooms'         => 0,
            'subscription_status' => 'active',
            'is_active'           => true,
            'is_verified'         => true,
            'trial_ends_at'       => now()->addDays(14),
            'settings'            => [
                'require_payment_on_booking' => false,
                'auto_confirm_reservations'  => false,
            ],
        ];
    }

    public function trial(): static
    {
        return $this->state(['subscription_status' => 'trial']);
    }

    public function suspended(): static
    {
        return $this->state([
            'subscription_status' => 'suspended',
            'is_active'           => false,
        ]);
    }
}
