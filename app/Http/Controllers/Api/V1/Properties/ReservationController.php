<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $property     = $request->get('current_property');
        $reservations = Reservation::where('property_id', $property->id)
            ->with(['guest:id,first_name,last_name,email,phone,nationality', 'roomType:id,name,code', 'room:id,room_number', 'bookingSource:id,name,color'])
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', (array) $s))
            ->when($request->guest_id, fn ($q, $id) => $q->where('guest_id', $id))
            ->when($request->room_type_id, fn ($q, $id) => $q->where('room_type_id', $id))
            ->when($request->check_in_from, fn ($q, $d) => $q->where('check_in_date', '>=', $d))
            ->when($request->check_in_to, fn ($q, $d) => $q->where('check_in_date', '<=', $d))
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q) =>
                    $q->where('reservation_number', 'ilike', "%{$s}%")
                      ->orWhere('ota_confirmation_number', 'ilike', "%{$s}%")
                      ->orWhereHas('guest', fn ($q) =>
                          $q->whereRaw("concat(first_name, ' ', last_name) ilike ?", ["%{$s}%"])
                            ->orWhere('email', 'ilike', "%{$s}%")
                      )
                )
            )
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $reservations->items(),
            'meta'    => [
                'total'        => $reservations->total(),
                'per_page'     => $reservations->perPage(),
                'current_page' => $reservations->currentPage(),
                'last_page'    => $reservations->lastPage(),
            ],
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after:from'],
        ]);

        $property     = $request->get('current_property');
        $reservations = Reservation::where('property_id', $property->id)
            ->with(['guest:id,first_name,last_name', 'room:id,room_number', 'roomType:id,name,code', 'bookingSource:id,name,color'])
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW])
            ->where('check_in_date', '<', $request->to)
            ->where('check_out_date', '>', $request->from)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $reservations,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'check_in_date'  => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults'         => ['sometimes', 'integer', 'min:1'],
        ]);

        $property  = $request->get('current_property');
        $checkIn   = Carbon::parse($request->check_in_date);
        $checkOut  = Carbon::parse($request->check_out_date);
        $nights    = $checkIn->diffInDays($checkOut);

        $roomTypes = RoomType::where('property_id', $property->id)
            ->where('is_active', true)
            ->with(['ratePlans' => fn ($q) => $q->where('is_active', true)->where('is_public', true)])
            ->orderBy('sort_order')
            ->get();

        $availability = $roomTypes->map(function ($rt) use ($property, $checkIn, $checkOut, $nights) {
            $avail = $this->reservationService->checkAvailability($property, $rt->id, $checkIn, $checkOut);

            return [
                'room_type'   => $rt->only(['id', 'name', 'code', 'base_rate', 'max_occupancy', 'max_adults', 'max_children', 'bed_type', 'amenities', 'photos']),
                'available'   => $avail['available'],
                'total_rooms' => $avail['total_rooms'],
                'is_available'=> $avail['is_available'],
                'nights'      => $nights,
                'rate_plans'  => $rt->ratePlans->map(fn ($rp) => [
                    'id'         => $rp->id,
                    'name'       => $rp->name,
                    'meal_plan'  => $rp->getMealPlanLabel(),
                    'rate'       => $rp->getRateForRoomType($rt->id) ?? $rt->base_rate,
                    'total'      => ($rp->getRateForRoomType($rt->id) ?? $rt->base_rate) * $nights,
                    'cancellation_policy' => $rp->getCancellationPolicyLabel(),
                ]),
            ];
        })->filter(fn ($item) => $item['is_available'])->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'check_in_date'  => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'nights'         => $nights,
                'room_types'     => $availability,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'guest_id'                => ['required', 'exists:guests,id'],
            'room_type_id'            => ['required', 'exists:room_types,id'],
            'room_id'                 => ['nullable', 'exists:rooms,id'],
            'rate_plan_id'            => ['nullable', 'exists:rate_plans,id'],
            'booking_source_id'       => ['nullable', 'exists:booking_sources,id'],
            'check_in_date'           => ['required', 'date'],
            'check_out_date'          => ['required', 'date', 'after:check_in_date'],
            'adults'                  => ['required', 'integer', 'min:1'],
            'children'                => ['sometimes', 'integer', 'min:0'],
            'infants'                 => ['sometimes', 'integer', 'min:0'],
            'room_rate'               => ['required', 'numeric', 'min:0'],
            'special_requests'        => ['nullable', 'string'],
            'internal_notes'          => ['nullable', 'string'],
            'ota_confirmation_number' => ['nullable', 'string'],
            'is_group_booking'        => ['sometimes', 'boolean'],
        ]);

        $reservation = $this->reservationService->createReservation($property, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reservation created.',
            'data'    => $reservation,
        ], 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $reservation->load([
            'guest',
            'roomType',
            'room',
            'ratePlan',
            'bookingSource',
            'createdBy:id,name',
            'checkedInBy:id,name',
            'checkedOutBy:id,name',
            'statusHistory.changedBy:id,name',
            'additionalGuests:id,first_name,last_name,email',
            'folio.items',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $reservation,
        ]);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        if (!in_array($reservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update reservation in current status.',
            ], 422);
        }

        $validated = $request->validate([
            'adults'           => ['sometimes', 'integer', 'min:1'],
            'children'         => ['sometimes', 'integer', 'min:0'],
            'special_requests' => ['sometimes', 'nullable', 'string'],
            'internal_notes'   => ['sometimes', 'nullable', 'string'],
            'room_id'          => ['sometimes', 'nullable', 'exists:rooms,id'],
            'booking_source_id'=> ['sometimes', 'nullable', 'exists:booking_sources,id'],
        ]);

        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Reservation updated.',
            'data'    => $reservation->fresh(),
        ]);
    }

    public function confirm(Request $request, Reservation $reservation): JsonResponse
    {
        try {
            $this->reservationService->confirm($reservation, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservation confirmed.',
            'data'    => $reservation->fresh(),
        ]);
    }

    public function checkIn(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['nullable', 'exists:rooms,id'],
        ]);

        try {
            $this->reservationService->checkIn($reservation, $request->user(), $validated['room_id'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest checked in successfully.',
            'data'    => $reservation->fresh(['room', 'guest']),
        ]);
    }

    public function checkOut(Request $request, Reservation $reservation): JsonResponse
    {
        try {
            $this->reservationService->checkOut($reservation, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest checked out successfully.',
            'data'    => $reservation->fresh(),
        ]);
    }

    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'fee'    => ['sometimes', 'numeric', 'min:0'],
        ]);

        try {
            $this->reservationService->cancel($reservation, $request->user(), $validated['reason'], $validated['fee'] ?? 0);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservation cancelled.',
            'data'    => $reservation->fresh(),
        ]);
    }

    public function noShow(Request $request, Reservation $reservation): JsonResponse
    {
        try {
            $this->reservationService->markNoShow($reservation, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservation marked as no-show.',
            'data'    => $reservation->fresh(),
        ]);
    }

    public function moveRoom(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'reason'  => ['nullable', 'string'],
        ]);

        if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
            return response()->json(['success' => false, 'message' => 'Can only move rooms for checked-in guests.'], 422);
        }

        $oldRoomId = $reservation->room_id;
        $reservation->update(['room_id' => $validated['room_id']]);

        // Update room statuses
        if ($oldRoomId) {
            \App\Models\Room::find($oldRoomId)?->update(['occupancy_status' => \App\Models\Room::OCCUPANCY_VACANT]);
        }
        \App\Models\Room::find($validated['room_id'])?->update(['occupancy_status' => \App\Models\Room::OCCUPANCY_OCCUPIED]);

        return response()->json([
            'success' => true,
            'message' => 'Room changed successfully.',
            'data'    => $reservation->fresh(['room']),
        ]);
    }

    public function folio(Reservation $reservation): JsonResponse
    {
        $folio = $reservation->folio()->with(['items' => fn ($q) => $q->orderBy('charge_date')])->first();

        return response()->json([
            'success' => true,
            'data'    => $folio,
        ]);
    }

    public function registrationCard(Reservation $reservation): JsonResponse
    {
        $card = $reservation->registrationCard()->first();

        return response()->json([
            'success' => true,
            'data'    => $card,
        ]);
    }
}
