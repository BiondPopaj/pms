<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => FeatureFlag::orderBy('key')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'                    => ['required', 'string', 'unique:feature_flags,key'],
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'enabled_globally'       => ['sometimes', 'boolean'],
            'enabled_for_plans'      => ['nullable', 'array'],
            'enabled_for_properties' => ['nullable', 'array'],
        ]);

        $flag = FeatureFlag::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $flag,
        ], 201);
    }

    public function show(FeatureFlag $featureFlag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $featureFlag,
        ]);
    }

    public function update(Request $request, FeatureFlag $featureFlag): JsonResponse
    {
        $validated = $request->validate([
            'name'                   => ['sometimes', 'string', 'max:255'],
            'description'            => ['sometimes', 'nullable', 'string'],
            'enabled_globally'       => ['sometimes', 'boolean'],
            'enabled_for_plans'      => ['sometimes', 'nullable', 'array'],
            'enabled_for_properties' => ['sometimes', 'nullable', 'array'],
        ]);

        $featureFlag->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $featureFlag->fresh(),
        ]);
    }

    public function destroy(FeatureFlag $featureFlag): JsonResponse
    {
        $featureFlag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Feature flag deleted.',
        ]);
    }

    public function toggle(FeatureFlag $featureFlag): JsonResponse
    {
        $featureFlag->toggle();

        return response()->json([
            'success' => true,
            'message' => $featureFlag->enabled_globally ? 'Feature enabled globally.' : 'Feature disabled globally.',
            'data'    => $featureFlag,
        ]);
    }
}
