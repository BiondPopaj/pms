<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => SubscriptionPlan::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'slug'                    => ['required', 'string', 'unique:subscription_plans,slug'],
            'description'             => ['nullable', 'string'],
            'max_properties'          => ['required', 'integer', 'min:1'],
            'max_rooms'               => ['required', 'integer', 'min:1'],
            'max_users'               => ['required', 'integer', 'min:1'],
            'price_monthly'           => ['required', 'numeric', 'min:0'],
            'price_yearly'            => ['required', 'numeric', 'min:0'],
            'currency'                => ['required', 'string', 'size:3'],
            'features'                => ['nullable', 'array'],
            'is_active'               => ['sometimes', 'boolean'],
            'sort_order'              => ['sometimes', 'integer'],
            'stripe_price_monthly_id' => ['nullable', 'string'],
            'stripe_price_yearly_id'  => ['nullable', 'string'],
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan created.',
            'data'    => $plan,
        ], 201);
    }

    public function show(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $subscriptionPlan->loadCount('properties');

        return response()->json([
            'success' => true,
            'data'    => $subscriptionPlan,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string'],
            'max_properties'=> ['sometimes', 'integer', 'min:1'],
            'max_rooms'     => ['sometimes', 'integer', 'min:1'],
            'max_users'     => ['sometimes', 'integer', 'min:1'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_yearly'  => ['sometimes', 'numeric', 'min:0'],
            'features'      => ['sometimes', 'nullable', 'array'],
            'is_active'     => ['sometimes', 'boolean'],
            'sort_order'    => ['sometimes', 'integer'],
        ]);

        $subscriptionPlan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated.',
            'data'    => $subscriptionPlan->fresh(),
        ]);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if ($subscriptionPlan->properties()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a plan with active properties.',
            ], 422);
        }

        $subscriptionPlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted.',
        ]);
    }
}
