<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $data = cache()->remember("dashboard.{$property->id}", now()->addMinutes(2), function () use ($property) {
            $today = today();

            return [
                'occupancy'   => $this->getOccupancyStats($property->id, $today),
                'revenue'     => $this->getRevenueStats($property->id, $today),
                'arrivals'    => $this->getArrivalsCount($property->id, $today),
                'departures'  => $this->getDeparturesCount($property->id, $today),
                'housekeeping'=> $this->getHousekeepingStats($property->id),
                'in_house'    => Reservation::where('property_id', $property->id)
                    ->where('status', Reservation::STATUS_CHECKED_IN)
                    ->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $today    = today();

        return response()->json([
            'success' => true,
            'data'    => [
                'date'       => $today->toDateString(),
                'arrivals'   => $this->getArrivalsCount($property->id, $today),
                'departures' => $this->getDeparturesCount($property->id, $today),
                'in_house'   => Reservation::where('property_id', $property->id)
                    ->where('status', Reservation::STATUS_CHECKED_IN)->count(),
                'no_shows'   => Reservation::where('property_id', $property->id)
                    ->where('status', Reservation::STATUS_NO_SHOW)
                    ->whereDate('check_in_date', $today)->count(),
            ],
        ]);
    }

    public function arrivals(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $date     = $request->get('date', today()->toDateString());

        $arrivals = Reservation::where('property_id', $property->id)
            ->with(['guest:id,first_name,last_name,email,phone,nationality', 'roomType:id,name,code', 'room:id,room_number'])
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
            ->whereDate('check_in_date', $date)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $arrivals,
            'total'   => $arrivals->count(),
        ]);
    }

    public function departures(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $date     = $request->get('date', today()->toDateString());

        $departures = Reservation::where('property_id', $property->id)
            ->with(['guest:id,first_name,last_name,email,phone', 'roomType:id,name,code', 'room:id,room_number'])
            ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])
            ->whereDate('check_out_date', $date)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $departures,
            'total'   => $departures->count(),
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function getOccupancyStats(int $propertyId, $today): array
    {
        $totalRooms   = Room::where('property_id', $propertyId)->where('is_active', true)->count();
        $occupiedRooms = Reservation::where('property_id', $propertyId)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->count();

        return [
            'total'     => $totalRooms,
            'occupied'  => $occupiedRooms,
            'vacant'    => max(0, $totalRooms - $occupiedRooms),
            'rate'      => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0,
        ];
    }

    private function getRevenueStats(int $propertyId, $today): array
    {
        $todayRevenue = Reservation::where('property_id', $propertyId)
            ->where('status', Reservation::STATUS_CHECKED_OUT)
            ->whereDate('checked_out_at', $today)
            ->sum('total_amount');

        $monthRevenue = Reservation::where('property_id', $propertyId)
            ->where('status', Reservation::STATUS_CHECKED_OUT)
            ->whereMonth('checked_out_at', $today->month)
            ->whereYear('checked_out_at', $today->year)
            ->sum('total_amount');

        return [
            'today' => $todayRevenue,
            'month' => $monthRevenue,
        ];
    }

    private function getArrivalsCount(int $propertyId, $today): int
    {
        return Reservation::where('property_id', $propertyId)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
            ->whereDate('check_in_date', $today)
            ->count();
    }

    private function getDeparturesCount(int $propertyId, $today): int
    {
        return Reservation::where('property_id', $propertyId)
            ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])
            ->whereDate('check_out_date', $today)
            ->count();
    }

    private function getHousekeepingStats(int $propertyId): array
    {
        return [
            'pending'    => HousekeepingTask::where('property_id', $propertyId)
                ->where('status', HousekeepingTask::STATUS_PENDING)
                ->whereDate('scheduled_date', today())
                ->count(),
            'in_progress'=> HousekeepingTask::where('property_id', $propertyId)
                ->where('status', HousekeepingTask::STATUS_IN_PROGRESS)
                ->count(),
            'completed'  => HousekeepingTask::where('property_id', $propertyId)
                ->where('status', HousekeepingTask::STATUS_COMPLETED)
                ->whereDate('scheduled_date', today())
                ->count(),
        ];
    }
}
