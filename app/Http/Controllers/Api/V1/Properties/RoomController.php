<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $rooms = Room::where('property_id', $property->id)
            ->with(['roomType:id,name,code'])
            ->when($request->room_type_id, fn ($q, $id) => $q->where('room_type_id', $id))
            ->when($request->housekeeping_status, fn ($q, $s) => $q->where('housekeeping_status', $s))
            ->when($request->occupancy_status, fn ($q, $s) => $q->where('occupancy_status', $s))
            ->when($request->floor, fn ($q, $f) => $q->where('floor', $f))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('room_number')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rooms,
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after:from'],
        ]);

        $property = $request->get('current_property');
        $from     = Carbon::parse($request->from);
        $to       = Carbon::parse($request->to);

        $rooms = Room::where('property_id', $property->id)
            ->where('is_active', true)
            ->with(['roomType:id,name,code'])
            ->orderBy('room_number')
            ->get();

        $reservations = Reservation::where('property_id', $property->id)
            ->with(['guest:id,first_name,last_name', 'roomType:id,name'])
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_PENDING,
            ])
            ->where('check_in_date', '<', $to)
            ->where('check_out_date', '>', $from)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'rooms'        => $rooms,
                'reservations' => $reservations,
                'from'         => $from->toDateString(),
                'to'           => $to->toDateString(),
            ],
        ]);
    }

    public function housekeepingBoard(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $rooms = Room::where('property_id', $property->id)
            ->where('is_active', true)
            ->with(['roomType:id,name,code', 'assignedHousekeeper:id,name'])
            ->orderBy('housekeeping_status')
            ->orderBy('room_number')
            ->get()
            ->groupBy('housekeeping_status');

        return response()->json([
            'success' => true,
            'data'    => $rooms,
        ]);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load(['roomType', 'assignedHousekeeper:id,name']);
        $room->load(['reservations' => fn ($q) =>
            $q->with(['guest:id,first_name,last_name'])
              ->where('check_out_date', '>=', today())
              ->orderBy('check_in_date')
              ->limit(5)
        ]);

        return response()->json([
            'success' => true,
            'data'    => $room,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number'  => ['required', 'string', 'max:20', "unique:rooms,room_number,NULL,id,property_id,{$property->id}"],
            'floor'        => ['nullable', 'string', 'max:10'],
            'building'     => ['nullable', 'string', 'max:50'],
            'view_type'    => ['nullable', 'string'],
            'is_smoking'   => ['sometimes', 'boolean'],
            'is_accessible'=> ['sometimes', 'boolean'],
            'notes'        => ['nullable', 'string'],
        ]);

        $room = Room::create([...$validated, 'property_id' => $property->id]);

        return response()->json([
            'success' => true,
            'message' => 'Room created.',
            'data'    => $room->load('roomType'),
        ], 201);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'room_number'  => ['sometimes', 'string', 'max:20', "unique:rooms,room_number,{$room->id},id,property_id,{$property->id}"],
            'floor'        => ['sometimes', 'nullable', 'string'],
            'building'     => ['sometimes', 'nullable', 'string'],
            'view_type'    => ['sometimes', 'nullable', 'string'],
            'is_smoking'   => ['sometimes', 'boolean'],
            'is_accessible'=> ['sometimes', 'boolean'],
            'is_active'    => ['sometimes', 'boolean'],
            'notes'        => ['sometimes', 'nullable', 'string'],
        ]);

        $room->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room updated.',
            'data'    => $room->fresh(['roomType']),
        ]);
    }

    public function destroy(Room $room): JsonResponse
    {
        $activeRes = Reservation::where('room_id', $room->id)
            ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CONFIRMED])
            ->exists();

        if ($activeRes) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a room with active reservations.',
            ], 422);
        }

        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room deleted.',
        ]);
    }

    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'housekeeping_status' => ['sometimes', Rule::in(array_keys(Room::housekeepingStatuses()))],
            'occupancy_status'    => ['sometimes', Rule::in(array_keys(Room::occupancyStatuses()))],
        ]);

        if (isset($validated['housekeeping_status']) && $validated['housekeeping_status'] === Room::HOUSEKEEPING_CLEAN) {
            $validated['last_cleaned_at'] = now();
        }

        $room->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room status updated.',
            'data'    => $room->fresh(),
        ]);
    }

    public function assignHousekeeper(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $room->update(['assigned_housekeeper_id' => $validated['user_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Housekeeper assigned.',
            'data'    => $room->fresh(['assignedHousekeeper:id,name']),
        ]);
    }
}
