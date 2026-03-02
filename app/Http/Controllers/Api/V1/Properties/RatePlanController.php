<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\RatePlan;
use App\Models\RoomRate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatePlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $ratePlans = RatePlan::where('property_id', $property->id)
            ->with(['roomTypes:id,name,code'])
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $ratePlans,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'code'                 => ['required', 'string', 'max:20'],
            'description'          => ['nullable', 'string'],
            'meal_plan'            => ['required', 'string'],
            'is_refundable'        => ['sometimes', 'boolean'],
            'cancellation_days'    => ['sometimes', 'integer', 'min:0'],
            'cancellation_penalty' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active'            => ['sometimes', 'boolean'],
            'is_public'            => ['sometimes', 'boolean'],
        ]);

        $ratePlan = RatePlan::create([...$validated, 'property_id' => $property->id]);

        return response()->json([
            'success' => true,
            'message' => 'Rate plan created.',
            'data'    => $ratePlan,
        ], 201);
    }

    public function show(RatePlan $ratePlan): JsonResponse
    {
        $ratePlan->load(['roomTypes']);

        return response()->json([
            'success' => true,
            'data'    => $ratePlan,
        ]);
    }

    public function update(Request $request, RatePlan $ratePlan): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'description'          => ['sometimes', 'nullable', 'string'],
            'meal_plan'            => ['sometimes', 'string'],
            'is_refundable'        => ['sometimes', 'boolean'],
            'cancellation_days'    => ['sometimes', 'integer', 'min:0'],
            'cancellation_penalty' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active'            => ['sometimes', 'boolean'],
            'is_public'            => ['sometimes', 'boolean'],
        ]);

        $ratePlan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rate plan updated.',
            'data'    => $ratePlan->fresh(),
        ]);
    }

    public function destroy(RatePlan $ratePlan): JsonResponse
    {
        $ratePlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rate plan deleted.',
        ]);
    }

    public function calendar(Request $request, RatePlan $ratePlan): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after:from'],
        ]);

        $rates = RoomRate::where('rate_plan_id', $ratePlan->id)
            ->whereBetween('date', [$request->from, $request->to])
            ->with('roomType:id,name,code')
            ->get()
            ->groupBy('date');

        return response()->json([
            'success' => true,
            'data'    => $rates,
        ]);
    }

    public function updateRates(Request $request, RatePlan $ratePlan): JsonResponse
    {
        $validated = $request->validate([
            'rates'               => ['required', 'array'],
            'rates.*.room_type_id'=> ['required', 'exists:room_types,id'],
            'rates.*.date'        => ['required', 'date'],
            'rates.*.rate'        => ['required', 'numeric', 'min:0'],
            'rates.*.availability'=> ['nullable', 'integer', 'min:0'],
            'rates.*.closed'      => ['sometimes', 'boolean'],
            'rates.*.min_stay'    => ['sometimes', 'integer', 'min:1'],
        ]);

        $property = $request->get('current_property');

        foreach ($validated['rates'] as $rateData) {
            RoomRate::updateOrCreate(
                [
                    'property_id'  => $property->id,
                    'rate_plan_id' => $ratePlan->id,
                    'room_type_id' => $rateData['room_type_id'],
                    'date'         => $rateData['date'],
                ],
                array_merge($rateData, ['property_id' => $property->id, 'rate_plan_id' => $ratePlan->id])
            );
        }

        return response()->json([
            'success' => true,
            'message' => count($validated['rates']) . ' rates updated.',
        ]);
    }
}
