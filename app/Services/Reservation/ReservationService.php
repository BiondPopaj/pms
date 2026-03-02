<?php

namespace App\Services\Reservation;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\HousekeepingTask;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\Room;
use App\Models\TaxConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /**
     * Check availability for a room type in a date range.
     */
    public function checkAvailability(
        Property $property,
        int      $roomTypeId,
        Carbon   $checkIn,
        Carbon   $checkOut,
        ?int     $excludeReservationId = null
    ): array {
        $rooms = Room::where('property_id', $property->id)
            ->where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->where('housekeeping_status', '!=', Room::HOUSEKEEPING_OUT_OF_ORDER)
            ->get();

        $totalRooms = $rooms->count();

        $conflictingQuery = Reservation::where('property_id', $property->id)
            ->where('room_type_id', $roomTypeId)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
            ])
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn);

        if ($excludeReservationId) {
            $conflictingQuery->where('id', '!=', $excludeReservationId);
        }

        $occupied  = $conflictingQuery->count();
        $available = max(0, $totalRooms - $occupied);

        return [
            'total_rooms'  => $totalRooms,
            'occupied'     => $occupied,
            'available'    => $available,
            'is_available' => $available > 0,
        ];
    }

    /**
     * Create a new reservation with folio.
     */
    public function createReservation(Property $property, array $data, User $createdBy): Reservation
    {
        return DB::transaction(function () use ($property, $data, $createdBy) {
            $checkIn  = Carbon::parse($data['check_in_date']);
            $checkOut = Carbon::parse($data['check_out_date']);
            $nights   = $checkIn->diffInDays($checkOut);

            // Calculate financials
            $roomRate  = $data['room_rate'];
            $totalRoom = $roomRate * $nights;

            [$totalTax, $taxBreakdown] = $this->calculateTaxes($property, $totalRoom);

            $totalAmount = $totalRoom + $totalTax;

            $reservation = Reservation::create([
                'property_id'        => $property->id,
                'status'             => Reservation::STATUS_PENDING,
                'guest_id'           => $data['guest_id'],
                'room_type_id'       => $data['room_type_id'],
                'room_id'            => $data['room_id'] ?? null,
                'rate_plan_id'       => $data['rate_plan_id'] ?? null,
                'booking_source_id'  => $data['booking_source_id'] ?? null,
                'check_in_date'      => $checkIn,
                'check_out_date'     => $checkOut,
                'nights'             => $nights,
                'adults'             => $data['adults'] ?? 1,
                'children'           => $data['children'] ?? 0,
                'infants'            => $data['infants'] ?? 0,
                'room_rate'          => $roomRate,
                'total_room'         => $totalRoom,
                'total_extras'       => 0,
                'total_tax'          => $totalTax,
                'total_discount'     => 0,
                'total_amount'       => $totalAmount,
                'total_paid'         => 0,
                'balance_due'        => $totalAmount,
                'currency'           => $property->currency,
                'payment_status'     => Reservation::PAYMENT_UNPAID,
                'special_requests'   => $data['special_requests'] ?? null,
                'internal_notes'     => $data['internal_notes'] ?? null,
                'ota_confirmation_number' => $data['ota_confirmation_number'] ?? null,
                'created_by'         => $createdBy->id,
                'is_group_booking'   => $data['is_group_booking'] ?? false,
                'metadata'           => $data['metadata'] ?? null,
            ]);

            // Create folio
            $folio = Folio::create([
                'property_id'    => $property->id,
                'reservation_id' => $reservation->id,
                'guest_id'       => $data['guest_id'],
                'status'         => Folio::STATUS_OPEN,
                'currency'       => $property->currency,
                'total_charges'  => $totalAmount,
                'total_payments' => 0,
                'balance'        => $totalAmount,
            ]);

            // Seed folio with room charges per night
            for ($day = 0; $day < $nights; $day++) {
                $chargeDate = $checkIn->copy()->addDays($day);
                FolioItem::create([
                    'property_id'  => $property->id,
                    'folio_id'     => $folio->id,
                    'type'         => 'charge',
                    'category'     => 'room',
                    'description'  => "Room charge - {$chargeDate->format('D, d M Y')}",
                    'quantity'     => 1,
                    'unit_price'   => $roomRate,
                    'amount'       => $roomRate,
                    'charge_date'  => $chargeDate,
                    'created_by'   => $createdBy->id,
                ]);
            }

            // Seed tax items
            foreach ($taxBreakdown as $tax) {
                FolioItem::create([
                    'property_id' => $property->id,
                    'folio_id'    => $folio->id,
                    'type'        => 'tax',
                    'category'    => 'tax',
                    'description' => $tax['name'],
                    'quantity'    => 1,
                    'unit_price'  => $tax['amount'],
                    'amount'      => $tax['amount'],
                    'tax_amount'  => $tax['amount'],
                    'tax_rate'    => $tax['rate'],
                    'tax_name'    => $tax['name'],
                    'charge_date' => $checkIn,
                    'created_by'  => $createdBy->id,
                ]);
            }

            // Auto-confirm if setting is enabled
            if ($property->getSetting('auto_confirm_reservations', false)) {
                $this->confirm($reservation, $createdBy);
            }

            // Log status history
            $this->logStatusChange($reservation, null, Reservation::STATUS_PENDING, $createdBy);

            return $reservation->load(['guest', 'roomType', 'room', 'ratePlan', 'bookingSource']);
        });
    }

    /**
     * Confirm a reservation.
     */
    public function confirm(Reservation $reservation, User $user): Reservation
    {
        if (!$reservation->canTransitionTo(Reservation::STATUS_CONFIRMED)) {
            throw new \RuntimeException("Cannot confirm reservation in status: {$reservation->status}");
        }

        $old = $reservation->status;

        $reservation->update([
            'status'       => Reservation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $this->logStatusChange($reservation, $old, Reservation::STATUS_CONFIRMED, $user);

        return $reservation->fresh();
    }

    /**
     * Check in a guest.
     */
    public function checkIn(Reservation $reservation, User $user, ?int $roomId = null): Reservation
    {
        if (!$reservation->canTransitionTo(Reservation::STATUS_CHECKED_IN)) {
            throw new \RuntimeException("Cannot check in reservation in status: {$reservation->status}");
        }

        $old = $reservation->status;

        $updateData = [
            'status'         => Reservation::STATUS_CHECKED_IN,
            'checked_in_at'  => now(),
            'checked_in_by'  => $user->id,
        ];

        if ($roomId) {
            $updateData['room_id'] = $roomId;
        }

        $reservation->update($updateData);

        // Update room occupancy status
        if ($reservation->room_id) {
            Room::where('id', $reservation->room_id)->update([
                'occupancy_status'    => Room::OCCUPANCY_OCCUPIED,
                'housekeeping_status' => Room::HOUSEKEEPING_DIRTY,
            ]);
        }

        $this->logStatusChange($reservation, $old, Reservation::STATUS_CHECKED_IN, $user);

        return $reservation->fresh();
    }

    /**
     * Check out a guest.
     */
    public function checkOut(Reservation $reservation, User $user): Reservation
    {
        if (!$reservation->canTransitionTo(Reservation::STATUS_CHECKED_OUT)) {
            throw new \RuntimeException("Cannot check out reservation in status: {$reservation->status}");
        }

        $old = $reservation->status;

        $reservation->update([
            'status'          => Reservation::STATUS_CHECKED_OUT,
            'checked_out_at'  => now(),
            'checked_out_by'  => $user->id,
        ]);

        // Update room status
        if ($reservation->room_id) {
            Room::where('id', $reservation->room_id)->update([
                'occupancy_status'    => Room::OCCUPANCY_VACANT,
                'housekeeping_status' => Room::HOUSEKEEPING_DIRTY,
            ]);

            // Create housekeeping task
            HousekeepingTask::create([
                'property_id'    => $reservation->property_id,
                'room_id'        => $reservation->room_id,
                'reservation_id' => $reservation->id,
                'type'           => HousekeepingTask::TYPE_CHECKOUT_CLEAN,
                'status'         => HousekeepingTask::STATUS_PENDING,
                'priority'       => HousekeepingTask::PRIORITY_HIGH,
                'scheduled_date' => today(),
            ]);
        }

        // Close folio
        $reservation->folio?->update([
            'status'    => Folio::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        // Update guest stats
        $reservation->guest?->updateStats();

        $this->logStatusChange($reservation, $old, Reservation::STATUS_CHECKED_OUT, $user);

        return $reservation->fresh();
    }

    /**
     * Cancel a reservation.
     */
    public function cancel(Reservation $reservation, User $user, string $reason, float $fee = 0): Reservation
    {
        if (!$reservation->canTransitionTo(Reservation::STATUS_CANCELLED)) {
            throw new \RuntimeException("Cannot cancel reservation in status: {$reservation->status}");
        }

        $old = $reservation->status;

        $reservation->update([
            'status'              => Reservation::STATUS_CANCELLED,
            'cancelled_at'        => now(),
            'cancelled_by'        => $user->id,
            'cancellation_reason' => $reason,
            'cancellation_fee'    => $fee,
        ]);

        // Free up room
        if ($reservation->room_id && $reservation->status === Reservation::STATUS_CHECKED_IN) {
            Room::where('id', $reservation->room_id)->update([
                'occupancy_status' => Room::OCCUPANCY_VACANT,
            ]);
        }

        $this->logStatusChange($reservation, $old, Reservation::STATUS_CANCELLED, $user, $reason);

        return $reservation->fresh();
    }

    /**
     * Mark as no-show.
     */
    public function markNoShow(Reservation $reservation, User $user): Reservation
    {
        if (!$reservation->canTransitionTo(Reservation::STATUS_NO_SHOW)) {
            throw new \RuntimeException("Cannot mark no-show for reservation in status: {$reservation->status}");
        }

        $old = $reservation->status;

        $reservation->update([
            'status'     => Reservation::STATUS_NO_SHOW,
            'cancelled_at' => now(),
        ]);

        $this->logStatusChange($reservation, $old, Reservation::STATUS_NO_SHOW, $user);

        return $reservation->fresh();
    }

    /**
     * Calculate taxes for an amount.
     */
    private function calculateTaxes(Property $property, float $amount): array
    {
        $taxes     = TaxConfig::where('property_id', $property->id)->active()->get();
        $totalTax  = 0;
        $breakdown = [];

        foreach ($taxes as $tax) {
            $taxAmount  = $tax->calculate($amount);
            $totalTax  += $taxAmount;
            $breakdown[] = [
                'name'   => $tax->name,
                'rate'   => $tax->rate,
                'amount' => $taxAmount,
                'type'   => $tax->type,
            ];
        }

        return [$totalTax, $breakdown];
    }

    private function logStatusChange(
        Reservation $reservation,
        ?string     $from,
        string      $to,
        User        $user,
        ?string     $reason = null
    ): void {
        ReservationStatusHistory::create([
            'reservation_id' => $reservation->id,
            'property_id'    => $reservation->property_id,
            'from_status'    => $from,
            'to_status'      => $to,
            'reason'         => $reason,
            'changed_by'     => $user->id,
        ]);
    }
}
