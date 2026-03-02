<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Catch-all for the SPA — returns the Inertia shell.
     * Vue Router handles all sub-routing client-side.
     */
    public function app(Request $request): Response
    {
        $property = $request->get('current_property');
        $user     = $request->user();

        return Inertia::render('App', [
            'auth' => [
                'user'     => $user->only(['id', 'ulid', 'name', 'email', 'avatar_url', 'is_platform_admin', 'locale', 'timezone', 'preferences']),
                'role'     => $user->getRoleForProperty($property),
                'property' => [
                    'id'             => $property->id,
                    'ulid'           => $property->ulid,
                    'name'           => $property->name,
                    'logo_url'       => $property->logo_url,
                    'property_type'  => $property->property_type,
                    'currency'       => $property->currency,
                    'timezone'       => $property->timezone,
                    'check_in_time'  => $property->check_in_time,
                    'check_out_time' => $property->check_out_time,
                    'settings'       => $property->settings,
                    'subscription'   => [
                        'status' => $property->subscription_status,
                        'plan'   => $property->subscriptionPlan?->slug,
                    ],
                ],
                'allProperties' => $user->activeProperties()->get(['properties.id', 'ulid', 'name', 'logo_path', 'property_type']),
            ],
        ]);
    }
}
