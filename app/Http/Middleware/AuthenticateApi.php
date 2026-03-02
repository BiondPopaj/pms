<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error'   => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$request->user()->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
                'error'   => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
