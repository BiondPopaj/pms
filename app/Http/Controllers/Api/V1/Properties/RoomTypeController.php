<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property   = $request->get('current_property');
        $roomTypes  = RoomType::where('property_id', $property->id)
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->withCount('rooms')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $roomTypes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'code'           => ['required', 'string', 'max:20', "unique:room_types,code,NULL,id,property_id,{$property->id}"],
            'description'    => ['nullable', 'string'],
            'base_occupancy' => ['required', 'integer', 'min:1'],
            'max_occupancy'  => ['required', 'integer', 'min:1'],
            'max_adults'     => ['required', 'integer', 'min:1'],
            'max_children'   => ['required', 'integer', 'min:0'],
            'bed_type'       => ['nullable', 'string'],
            'size_sqm'       => ['nullable', 'numeric', 'min:0'],
            'amenities'      => ['nullable', 'array'],
            'base_rate'      => ['required', 'numeric', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'sort_order'     => ['sometimes', 'integer'],
        ]);

        $roomType = RoomType::create([...$validated, 'property_id' => $property->id]);

        return response()->json([
            'success' => true,
            'message' => 'Room type created.',
            'data'    => $roomType,
        ], 201);
    }

    public function show(Request $request, RoomType $roomType): JsonResponse
    {
        $roomType->load(['rooms:id,room_number,floor,housekeeping_status,occupancy_status,is_active']);
        $roomType->loadCount('rooms');

        return response()->json([
            'success' => true,
            'data'    => $roomType,
        ]);
    }

    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'code'           => ['sometimes', 'string', 'max:20', "unique:room_types,code,{$roomType->id},id,property_id,{$property->id}"],
            'description'    => ['sometimes', 'nullable', 'string'],
            'base_occupancy' => ['sometimes', 'integer', 'min:1'],
            'max_occupancy'  => ['sometimes', 'integer', 'min:1'],
            'max_adults'     => ['sometimes', 'integer', 'min:1'],
            'max_children'   => ['sometimes', 'integer', 'min:0'],
            'bed_type'       => ['sometimes', 'nullable', 'string'],
            'size_sqm'       => ['sometimes', 'nullable', 'numeric'],
            'amenities'      => ['sometimes', 'nullable', 'array'],
            'base_rate'      => ['sometimes', 'numeric', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'sort_order'     => ['sometimes', 'integer'],
        ]);

        $roomType->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room type updated.',
            'data'    => $roomType->fresh(),
        ]);
    }

    public function destroy(RoomType $roomType): JsonResponse
    {
        if ($roomType->rooms()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a room type that has rooms.',
            ], 422);
        }

        $roomType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room type deleted.',
        ]);
    }
}
