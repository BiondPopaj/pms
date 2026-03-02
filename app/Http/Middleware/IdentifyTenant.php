<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifies the current tenant (property) from the request.
 *
 * Resolution order:
 * 1. X-Property-ID header (API requests)
 * 2. property_ulid route parameter
 * 3. Session (web requests)
 * 4. User's first active property (last resort)
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $property = $this->resolveProperty($request);

        if (!$property) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property context is required.',
                    'error'   => 'NO_PROPERTY_CONTEXT',
                ], 400);
            }
            return redirect()->route('properties.select');
        }

        if (!$property->is_active) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This property account is inactive.',
                    'error'   => 'PROPERTY_INACTIVE',
                ], 403);
            }
            abort(403, 'Property account is inactive.');
        }

        if (!$property->isSubscriptionActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property subscription has expired.',
                    'error'   => 'SUBSCRIPTION_EXPIRED',
                ], 402);
            }
        }

        // Make property available throughout the request lifecycle
        $request->attributes->set('current_property', $property);
        app()->instance('current_property', $property);

        // Store in session for web requests
        if (!$request->expectsJson()) {
            session(['current_property_id' => $property->id]);
        }

        return $next($request);
    }

    private function resolveProperty(Request $request): ?Property
    {
        // 1. API header: X-Property-ID (accepts ULID or ID)
        if ($header = $request->header('X-Property-ID')) {
            return $this->findProperty($header);
        }

        // 2. Route parameter
        if ($ulid = $request->route('property_ulid')) {
            return $this->findProperty($ulid);
        }

        // 3. Authenticated user's session/preference
        if ($user = $request->user()) {
            // Try session-stored property
            if ($sessionPropertyId = session('current_property_id')) {
                $property = $this->findProperty($sessionPropertyId);
                if ($property && $user->canAccessProperty($property)) {
                    return $property;
                }
            }

            // Default to user's first active property
            return $user->activeProperties()->first();
        }

        return null;
    }

    private function findProperty(mixed $identifier): ?Property
    {
        $cacheKey = "property.resolve.{$identifier}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($identifier) {
            // Try ULID first
            if (is_string($identifier) && strlen($identifier) === 26) {
                return Property::where('ulid', $identifier)->first();
            }

            // Try numeric ID
            if (is_numeric($identifier)) {
                return Property::find($identifier);
            }

            // Try slug
            return Property::where('slug', $identifier)->first();
        });
    }
}
