<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $checkIn  = fake()->dateTimeBetween('now', '+30 days');
        $nights   = fake()->numberBetween(1, 7);
        $checkOut = (clone $checkIn)->modify("+{$nights} days");
        $rate     = fake()->randomFloat(2, 80, 400);

        return [
            'property_id'        => Property::factory(),
            'reservation_number' => 'RES-'.strtoupper(Str::random(8)),
            'status'             => 'pending',
            'guest_id'           => Guest::factory(),
            'room_type_id'       => RoomType::factory(),
            'room_id'            => null,
            'rate_plan_id'       => null,
            'booking_source_id'  => null,
            'check_in_date'      => $checkIn->format('Y-m-d'),
            'check_out_date'     => $checkOut->format('Y-m-d'),
            'nights'             => $nights,
            'adults'             => fake()->numberBetween(1, 3),
            'children'           => 0,
            'infants'            => 0,
            'room_rate'          => $rate,
            'total_room'         => $rate * $nights,
            'total_extras'       => 0,
            'total_tax'          => 0,
            'total_discount'     => 0,
            'total_amount'       => $rate * $nights,
            'total_paid'         => 0,
            'balance_due'        => $rate * $nights,
            'currency'           => 'USD',
            'payment_status'     => 'unpaid',
            'is_group_booking'   => false,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    public function checkedIn(): static
    {
        return $this->state([
            'status'        => 'checked_in',
            'checked_in_at' => now(),
            'check_in_date' => today()->toDateString(),
        ]);
    }

    public function checkedOut(): static
    {
        return $this->state([
            'status'         => 'checked_out',
            'checked_out_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
