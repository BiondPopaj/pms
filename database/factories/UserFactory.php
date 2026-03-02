<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'email_verified_at'  => now(),
            'password'           => bcrypt('password'),
            'phone'              => fake()->optional()->phoneNumber(),
            'locale'             => 'en',
            'timezone'           => 'UTC',
            'is_platform_admin'  => false,
            'is_active'          => true,
            'two_factor_enabled' => false,
            'preferences'        => null,
            'remember_token'     => Str::random(10),
        ];
    }

    public function platformAdmin(): static
    {
        return $this->state(['is_platform_admin' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
