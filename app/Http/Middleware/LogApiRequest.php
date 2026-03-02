<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Log slow requests (> 1000ms)
        if ($duration > 1000) {
            Log::warning('Slow API request', [
                'method'      => $request->method(),
                'url'         => $request->fullUrl(),
                'duration_ms' => $duration,
                'user_id'     => $request->user()?->id,
                'property_id' => $request->get('current_property')?->id,
                'status'      => $response->getStatusCode(),
            ]);
        }

        // Add timing header
        $response->headers->set('X-Response-Time', "{$duration}ms");

        return $response;
    }
}
