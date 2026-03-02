<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the authenticated user has access to the current tenant property.
 * Must run AFTER IdentifyTenant middleware.
 *
 * Usage in routes:
 *   ->middleware(['tenant', 'property.access'])
 *   ->middleware(['tenant', 'property.access:manager'])  // minimum role
 *   ->middleware(['tenant', 'property.access:manager,owner'])  // any of these roles
 */
class EnsurePropertyAccess
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user     = $request->user();
        $property = $request->get('current_property');

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'error'   => 'UNAUTHENTICATED',
                ], 401);
            }
            return redirect()->route('auth.login');
        }

        // Platform admins bypass all property access checks
        if ($user->is_platform_admin) {
            return $next($request);
        }

        if (!$property) {
            abort(400, 'No property context available.');
        }

        // Check basic access
        if (!$user->canAccessProperty($property)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this property.',
                    'error'   => 'PROPERTY_ACCESS_DENIED',
                ], 403);
            }
            abort(403, 'Access to this property is denied.');
        }

        // Check specific role requirement
        if (!empty($roles)) {
            $hasRole = $user->hasRoleOnProperty($roles, $property);

            if (!$hasRole) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have the required role for this action.',
                        'error'   => 'INSUFFICIENT_ROLE',
                        'required_roles' => $roles,
                        'your_role' => $user->getRoleForProperty($property),
                    ], 403);
                }
                abort(403, 'Insufficient role.');
            }
        }

        // Attach role to request for downstream use
        $request->attributes->set(
            'property_role',
            $user->getRoleForProperty($property)
        );

        return $next($request);
    }
}
