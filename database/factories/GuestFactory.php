<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id'  => Property::factory(),
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'email'        => fake()->optional(0.8)->safeEmail(),
            'phone'        => fake()->optional(0.7)->phoneNumber(),
            'nationality'  => fake()->optional()->countryCode(),
            'language'     => 'en',
            'date_of_birth'=> fake()->optional()->dateTimeBetween('-80 years', '-18 years'),
            'gender'       => fake()->optional()->randomElement(['male', 'female']),
            'country'      => fake()->optional()->countryCode(),
            'city'         => fake()->optional()->city(),
            'total_revenue'=> 0,
            'total_stays'  => 0,
            'vip_status'   => null,
            'is_blacklisted'=> false,
        ];
    }

    public function vip(string $level = 'gold'): static
    {
        return $this->state(['vip_status' => $level]);
    }

    public function blacklisted(): static
    {
        return $this->state([
            'is_blacklisted'    => true,
            'blacklist_reason'  => 'Unpaid invoices',
        ]);
    }
}
