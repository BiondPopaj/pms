<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertySwitcherController extends Controller
{
    public function index(Request $request): Response
    {
        $properties = $request->user()
            ->activeProperties()
            ->get(['properties.id', 'ulid', 'name', 'logo_path', 'property_type', 'city', 'country', 'subscription_status']);

        return Inertia::render('Auth/SelectProperty', [
            'properties' => $properties,
        ]);
    }

    public function switch(Request $request, Property $property): RedirectResponse
    {
        $user = $request->user();

        if (!$user->canAccessProperty($property)) {
            abort(403, 'Access denied to this property.');
        }

        session(['current_property_id' => $property->id]);

        return redirect('/dashboard');
    }
}
