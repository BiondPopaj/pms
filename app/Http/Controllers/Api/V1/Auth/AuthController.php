<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\AuthUserResource;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = 'login:'.$request->ip().':'.$request->input('email');

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $user = User::where('email', $request->input('email'))
                    ->where('is_active', true)
                    ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Revoke previous tokens from this device if exists
        if ($deviceName = $request->input('device_name')) {
            $user->tokens()->where('name', $deviceName)->delete();
        }

        $tokenName   = $request->input('device_name', 'api');
        $abilities   = $this->getAbilitiesForUser($user);
        $token       = $user->createToken($tokenName, $abilities, now()->addDays(30));

        // Update login metadata
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Audit
        $this->auditService->log(
            event: 'login',
            user: $user,
            description: "User logged in from {$request->ip()}",
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(30)->toISOString(),
                'user'       => new AuthUserResource($user),
                'properties' => $user->activeProperties()->get(),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        $this->auditService->log(
            event: 'logout',
            user: $request->user(),
            description: 'User logged out.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All sessions terminated.',
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'activeProperties:id,ulid,name,logo_path,property_type,currency,timezone',
        ]);

        return response()->json([
            'success' => true,
            'data'    => new AuthUserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $user     = $request->user();
        $oldToken = $user->currentAccessToken();

        // Create new token
        $newToken = $user->createToken(
            $oldToken->name,
            $oldToken->abilities,
            now()->addDays(30)
        );

        // Delete old token
        $oldToken->delete();

        return response()->json([
            'success'    => true,
            'data'       => [
                'token'      => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(30)->toISOString(),
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->select('id', 'name', 'last_used_at', 'created_at', 'expires_at', 'ip_address', 'device_name')
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'device_name'  => $t->device_name,
                'ip_address'   => $t->ip_address,
                'last_used_at' => $t->last_used_at,
                'created_at'   => $t->created_at,
                'expires_at'   => $t->expires_at,
                'is_current'   => $t->id === $request->user()->currentAccessToken()->id,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    /**
     * DELETE /api/v1/auth/sessions/{tokenId}
     */
    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($tokenId);

        if ($token->id === $request->user()->currentAccessToken()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke the current session.',
            ], 422);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session revoked.',
        ]);
    }

    /**
     * PATCH /api/v1/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data'    => new AuthUserResource($request->user()->fresh()),
        ]);
    }

    /**
     * PATCH /api/v1/auth/password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all other tokens for security
        $request->user()
                ->tokens()
                ->where('id', '!=', $request->user()->currentAccessToken()->id)
                ->delete();

        $this->auditService->log(
            event: 'password_changed',
            user: $request->user(),
            description: 'User changed their password.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Other sessions have been terminated.',
        ]);
    }

    /**
     * Resolve token abilities based on user type.
     */
    private function getAbilitiesForUser(User $user): array
    {
        if ($user->is_platform_admin) {
            return ['*']; // Full access
        }

        // Standard abilities - refined by role in controllers/policies
        return [
            'reservations:read',
            'reservations:write',
            'guests:read',
            'guests:write',
            'rooms:read',
            'reports:read',
            'housekeeping:read',
            'housekeeping:write',
            'billing:read',
        ];
    }
}
