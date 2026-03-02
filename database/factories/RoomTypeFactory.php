<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        $types = ['Standard King', 'Deluxe Twin', 'Junior Suite', 'Executive Room'];
        $codes = ['STK', 'DXT', 'JST', 'EXE'];
        $i     = array_rand($types);

        return [
            'property_id'    => Property::factory(),
            'name'           => $types[$i],
            'code'           => $codes[$i].fake()->unique()->numerify('##'),
            'description'    => fake()->sentence(),
            'base_occupancy' => 2,
            'max_occupancy'  => 3,
            'max_adults'     => 2,
            'max_children'   => 1,
            'bed_type'       => fake()->randomElement(['king', 'twin', 'double', 'queen']),
            'size_sqm'       => fake()->numberBetween(20, 80),
            'amenities'      => ['wifi', 'tv', 'air_conditioning'],
            'base_rate'      => fake()->randomFloat(2, 80, 500),
            'is_active'      => true,
            'sort_order'     => 0,
        ];
    }
}
