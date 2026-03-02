<?php

namespace App\Services\NightAudit;

use App\Models\Folio;
use App\Models\NightAudit;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NightAuditService
{
    public function runAudit(Property $property, Carbon $date, User $user): NightAudit
    {
        return DB::transaction(function () use ($property, $date, $user) {

            // Check if already completed
            $existing = NightAudit::where('property_id', $property->id)
                ->where('audit_date', $date->toDateString())
                ->first();

            if ($existing && $existing->isCompleted()) {
                throw new \RuntimeException("Night audit for {$date->toDateString()} already completed.");
            }

            $audit = $existing ?? NightAudit::create([
                'property_id' => $property->id,
                'audit_date'  => $date->toDateString(),
                'status'      => NightAudit::STATUS_IN_PROGRESS,
            ]);

            $audit->update(['status' => NightAudit::STATUS_IN_PROGRESS]);

            // ── 1. Post room charges for in-house reservations ─────────────
            $inHouseReservations = Reservation::where('property_id', $property->id)
                ->where('status', Reservation::STATUS_CHECKED_IN)
                ->where('check_out_date', '>', $date)
                ->get();

            $roomRevenue = 0;
            foreach ($inHouseReservations as $res) {
                $roomRevenue += $res->room_rate;
            }

            // ── 2. Handle no-shows ────────────────────────────────────────
            $noShows = Reservation::where('property_id', $property->id)
                ->where('status', Reservation::STATUS_CONFIRMED)
                ->where('check_in_date', $date->toDateString())
                ->get();

            foreach ($noShows as $res) {
                $res->update([
                    'status'      => Reservation::STATUS_NO_SHOW,
                    'cancelled_at'=> now(),
                ]);
            }

            // ── 3. Calculate metrics ──────────────────────────────────────
            $totalRooms   = Room::where('property_id', $property->id)->where('is_active', true)->count();
            $occupiedCount = $inHouseReservations->count();
            $arrivals     = Reservation::where('property_id', $property->id)
                ->where('check_in_date', $date->toDateString())
                ->where('status', Reservation::STATUS_CHECKED_IN)
                ->count();
            $departures   = Reservation::where('property_id', $property->id)
                ->where('check_out_date', $date->toDateString())
                ->where('status', Reservation::STATUS_CHECKED_OUT)
                ->count();

            $occupancyRate = $totalRooms > 0 ? round(($occupiedCount / $totalRooms) * 100, 2) : 0;
            $adr           = $occupiedCount > 0 ? round($roomRevenue / $occupiedCount, 2) : 0;
            $revpar        = $totalRooms > 0 ? round($roomRevenue / $totalRooms, 2) : 0;

            // ── 4. Other revenue (F&B, extras from folios) ────────────────
            $otherRevenue = 0; // Would sum non-room folio items posted today

            $totalRevenue = $roomRevenue + $otherRevenue;

            // ── 5. Complete audit ─────────────────────────────────────────
            $audit->update([
                'status'          => NightAudit::STATUS_COMPLETED,
                'total_revenue'   => $totalRevenue,
                'room_revenue'    => $roomRevenue,
                'other_revenue'   => $otherRevenue,
                'total_tax'       => 0,
                'rooms_occupied'  => $occupiedCount,
                'rooms_available' => $totalRooms,
                'occupancy_rate'  => $occupancyRate,
                'adr'             => $adr,
                'revpar'          => $revpar,
                'arrivals'        => $arrivals,
                'departures'      => $departures,
                'no_shows'        => $noShows->count(),
                'completed_by'    => $user->id,
                'completed_at'    => now(),
                'summary'         => [
                    'in_house'          => $occupiedCount,
                    'room_revenue'      => $roomRevenue,
                    'occupancy_percent' => $occupancyRate,
                ],
            ]);

            return $audit->fresh(['completedBy']);
        });
    }

    public function getReport(Property $property, Carbon $date): array
    {
        $audit = NightAudit::where('property_id', $property->id)
            ->where('audit_date', $date->toDateString())
            ->first();

        if (!$audit) {
            return ['available' => false, 'message' => 'No audit found for this date.'];
        }

        return [
            'available'  => true,
            'audit'      => $audit->toArray(),
            'generated_at' => now()->toISOString(),
        ];
    }
}
