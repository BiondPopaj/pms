<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PropertyAdminResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $property->load(['subscriptionPlan:id,name,slug,features']);

        return response()->json([
            'success' => true,
            'data'    => new PropertyAdminResource($property),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $this->authorize('update', $property);

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'phone'          => ['sometimes', 'nullable', 'string', 'max:30'],
            'website'        => ['sometimes', 'nullable', 'url'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'address_line1'  => ['sometimes', 'nullable', 'string'],
            'address_line2'  => ['sometimes', 'nullable', 'string'],
            'city'           => ['sometimes', 'nullable', 'string'],
            'state'          => ['sometimes', 'nullable', 'string'],
            'postal_code'    => ['sometimes', 'nullable', 'string'],
            'country'        => ['sometimes', 'string', 'size:2'],
            'timezone'       => ['sometimes', 'string', 'timezone'],
            'currency'       => ['sometimes', 'string', 'size:3'],
            'locale'         => ['sometimes', 'string'],
            'star_rating'    => ['sometimes', 'nullable', 'integer', 'between:1,5'],
            'check_in_time'  => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'check_out_time' => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'settings'       => ['sometimes', 'array'],
        ]);

        $property->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Property updated.',
            'data'    => new PropertyAdminResource($property->fresh(['subscriptionPlan'])),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $property = $request->get('current_property');
        $this->authorize('update', $property);

        if ($property->logo_path) {
            Storage::disk('public')->delete($property->logo_path);
        }

        $path = $request->file('logo')->store("properties/{$property->id}/logos", 'public');
        $property->update(['logo_path' => $path]);

        return response()->json([
            'success'  => true,
            'message'  => 'Logo uploaded.',
            'logo_url' => $property->logo_url,
        ]);
    }
}
