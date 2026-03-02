<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default across all Inertia pages.
     */
    public function share(Request $request): array
    {
        $user     = $request->user();
        $property = $request->get('current_property');

        return array_merge(parent::share($request), [

            // ─── Auth ─────────────────────────────────────────────────────
            'auth' => [
                'user' => $user ? [
                    'id'                 => $user->id,
                    'ulid'               => $user->ulid,
                    'name'               => $user->name,
                    'email'              => $user->email,
                    'avatar_url'         => $user->avatar_url,
                    'locale'             => $user->locale,
                    'timezone'           => $user->timezone,
                    'is_platform_admin'  => $user->is_platform_admin,
                    'preferences'        => $user->preferences ?? [],
                ] : null,
                'role' => $user && $property
                    ? $user->getRoleForProperty($property)
                    : null,
            ],

            // ─── Current Property ──────────────────────────────────────────
            'property' => $property ? [
                'id'             => $property->id,
                'ulid'           => $property->ulid,
                'name'           => $property->name,
                'slug'           => $property->slug,
                'currency'       => $property->currency,
                'timezone'       => $property->timezone,
                'locale'         => $property->locale,
                'logo_url'       => $property->logo_url,
                'property_type'  => $property->property_type,
                'check_in_time'  => $property->check_in_time,
                'check_out_time' => $property->check_out_time,
                'settings'       => $property->settings,
                'subscription_status' => $property->subscription_status,
                'plan_name'      => $property->subscriptionPlan?->name,
            ] : null,

            // ─── User's accessible properties (for switcher) ───────────────
            'user_properties' => $user && !$user->is_platform_admin
                ? $user->activeProperties()
                        ->select('properties.id', 'properties.ulid', 'properties.name', 'properties.logo_path', 'properties.property_type')
                        ->get()
                        ->map(fn ($p) => [
                            'id'      => $p->id,
                            'ulid'    => $p->ulid,
                            'name'    => $p->name,
                            'logo_url'=> $p->logo_url,
                            'type'    => $p->property_type,
                        ])
                : null,

            // ─── Flash Messages ────────────────────────────────────────────
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],

            // ─── UI Config ────────────────────────────────────────────────
            'app' => [
                'name'    => config('app.name'),
                'version' => config('app.version', '1.0.0'),
                'env'     => config('app.env'),
            ],
        ]);
    }
}
