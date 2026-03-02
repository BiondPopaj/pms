<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $guests = Guest::where('property_id', $property->id)
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->vip_status, fn ($q, $s) => $q->where('vip_status', $s))
            ->when($request->boolean('blacklisted'), fn ($q) => $q->where('is_blacklisted', true))
            ->when($request->nationality, fn ($q, $n) => $q->where('nationality', $n))
            ->withCount('reservations')
            ->orderBy('last_name')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $guests->items(),
            'meta'    => [
                'total'        => $guests->total(),
                'per_page'     => $guests->perPage(),
                'current_page' => $guests->currentPage(),
                'last_page'    => $guests->lastPage(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2']]);

        $property = $request->get('current_property');

        $guests = Guest::where('property_id', $property->id)
            ->search($request->q)
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'nationality', 'vip_status']);

        return response()->json([
            'success' => true,
            'data'    => $guests,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'nationality'   => ['nullable', 'string', 'size:2'],
            'language'      => ['nullable', 'string', 'max:5'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender'        => ['nullable', 'in:male,female,other'],
            'address_line1' => ['nullable', 'string'],
            'address_line2' => ['nullable', 'string'],
            'city'          => ['nullable', 'string'],
            'state'         => ['nullable', 'string'],
            'postal_code'   => ['nullable', 'string'],
            'country'       => ['nullable', 'string', 'size:2'],
            'company_name'  => ['nullable', 'string'],
            'vat_number'    => ['nullable', 'string'],
            'id_type'       => ['nullable', 'string'],
            'id_number'     => ['nullable', 'string'],
            'id_expiry'     => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
        ]);

        $guest = Guest::create([...$validated, 'property_id' => $property->id]);

        return response()->json([
            'success' => true,
            'message' => 'Guest created.',
            'data'    => $guest,
        ], 201);
    }

    public function show(Guest $guest): JsonResponse
    {
        $guest->load(['reservations' => fn ($q) =>
            $q->with(['roomType:id,name', 'room:id,room_number'])
              ->latest('check_in_date')
              ->limit(10)
        ]);

        return response()->json([
            'success' => true,
            'data'    => $guest,
        ]);
    }

    public function update(Request $request, Guest $guest): JsonResponse
    {
        $validated = $request->validate([
            'first_name'    => ['sometimes', 'string', 'max:255'],
            'last_name'     => ['sometimes', 'string', 'max:255'],
            'email'         => ['sometimes', 'nullable', 'email'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:30'],
            'nationality'   => ['sometimes', 'nullable', 'string', 'size:2'],
            'language'      => ['sometimes', 'nullable', 'string'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender'        => ['sometimes', 'nullable', 'in:male,female,other'],
            'address_line1' => ['sometimes', 'nullable', 'string'],
            'city'          => ['sometimes', 'nullable', 'string'],
            'country'       => ['sometimes', 'nullable', 'string', 'size:2'],
            'company_name'  => ['sometimes', 'nullable', 'string'],
            'vat_number'    => ['sometimes', 'nullable', 'string'],
            'notes'         => ['sometimes', 'nullable', 'string'],
            'internal_notes'=> ['sometimes', 'nullable', 'string'],
            'vip_status'    => ['sometimes', 'nullable', 'in:silver,gold,platinum'],
            'preferences'   => ['sometimes', 'nullable', 'array'],
            'is_blacklisted'=> ['sometimes', 'boolean'],
            'blacklist_reason'=> ['sometimes', 'nullable', 'string'],
        ]);

        $guest->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Guest updated.',
            'data'    => $guest->fresh(),
        ]);
    }

    public function destroy(Guest $guest): JsonResponse
    {
        $hasActiveReservations = Reservation::where('guest_id', $guest->id)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
            ->exists();

        if ($hasActiveReservations) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a guest with active reservations.',
            ], 422);
        }

        $guest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Guest deleted.',
        ]);
    }

    public function history(Guest $guest): JsonResponse
    {
        $reservations = Reservation::where('guest_id', $guest->id)
            ->with(['property:id,name', 'roomType:id,name', 'room:id,room_number'])
            ->orderByDesc('check_in_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $reservations,
        ]);
    }

    public function uploadDocument(Request $request, Guest $guest): JsonResponse
    {
        $request->validate([
            'document'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'type'      => ['required', 'in:passport,id_card,driver_license,visa'],
        ]);

        $path = $request->file('document')->store(
            "properties/{$guest->property_id}/guests/{$guest->id}/documents",
            'private'
        );

        $guest->update(['id_document_path' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded.',
        ]);
    }
}
