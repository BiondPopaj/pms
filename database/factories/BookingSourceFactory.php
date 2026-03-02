<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingSourceFactory extends Factory
{
    public function definition(): array
    {
        $sources = [
            ['name' => 'Direct',      'code' => 'DIRECT',  'type' => 'direct', 'commission_percent' => 0],
            ['name' => 'Walk-in',     'code' => 'WALKIN',  'type' => 'direct', 'commission_percent' => 0],
            ['name' => 'Booking.com', 'code' => 'BOOKING', 'type' => 'ota',    'commission_percent' => 15],
            ['name' => 'Expedia',     'code' => 'EXPEDIA', 'type' => 'ota',    'commission_percent' => 18],
        ];
        $source = fake()->randomElement($sources);

        return [
            'property_id'         => Property::factory(),
            'name'                => $source['name'],
            'code'                => $source['code'].fake()->unique()->numerify('##'),
            'type'                => $source['type'],
            'commission_percent'  => $source['commission_percent'],
            'color'               => fake()->hexColor(),
            'is_active'           => true,
        ];
    }
}
