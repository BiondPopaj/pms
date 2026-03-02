<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureFlag
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $property = $request->get('current_property');

        if (!$property) {
            return $next($request);
        }

        if (!$property->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Feature '{$feature}' is not available on your current plan.",
                    'error'   => 'FEATURE_NOT_AVAILABLE',
                    'feature' => $feature,
                ], 402);
            }
            abort(402, "Feature '{$feature}' is not available on your current plan.");
        }

        return $next($request);
    }
}
