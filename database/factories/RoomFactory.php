<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id'         => Property::factory(),
            'room_type_id'        => RoomType::factory(),
            'room_number'         => fake()->unique()->numerify('###'),
            'floor'               => fake()->numberBetween(1, 10),
            'building'            => null,
            'view_type'           => fake()->optional()->randomElement(['sea', 'garden', 'pool', 'city']),
            'housekeeping_status' => 'clean',
            'occupancy_status'    => 'vacant',
            'is_smoking'          => false,
            'is_accessible'       => false,
            'is_active'           => true,
            'notes'               => null,
        ];
    }

    public function dirty(): static
    {
        return $this->state(['housekeeping_status' => 'dirty']);
    }

    public function occupied(): static
    {
        return $this->state(['occupancy_status' => 'occupied']);
    }

    public function outOfOrder(): static
    {
        return $this->state(['housekeeping_status' => 'out_of_order']);
    }
}
