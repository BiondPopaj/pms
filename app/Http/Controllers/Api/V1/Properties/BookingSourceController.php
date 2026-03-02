<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\BookingSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $sources  = BookingSource::where('property_id', $property->id)
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $sources]);
    }

    public function store(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'code'               => ['required', 'string', 'max:30'],
            'type'               => ['required', 'in:direct,ota,gds,corporate,group'],
            'commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'color'              => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $source = BookingSource::create([...$validated, 'property_id' => $property->id]);

        return response()->json(['success' => true, 'message' => 'Booking source created.', 'data' => $source], 201);
    }

    public function show(BookingSource $bookingSource): JsonResponse
    {
        $bookingSource->loadCount('reservations');
        return response()->json(['success' => true, 'data' => $bookingSource]);
    }

    public function update(Request $request, BookingSource $bookingSource): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'color'              => ['sometimes', 'string'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $bookingSource->update($validated);

        return response()->json(['success' => true, 'message' => 'Booking source updated.', 'data' => $bookingSource->fresh()]);
    }

    public function destroy(BookingSource $bookingSource): JsonResponse
    {
        if ($bookingSource->reservations()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete a booking source with reservations.'], 422);
        }
        $bookingSource->delete();
        return response()->json(['success' => true, 'message' => 'Booking source deleted.']);
    }
}
