<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q) => $q
                    ->where('name', 'ilike', "%{$s}%")
                    ->orWhere('email', 'ilike', "%{$s}%")
                )
            )
            ->when($request->boolean('platform_admins'), fn ($q) =>
                $q->platformAdmins()
            )
            ->when(isset($request->active), fn ($q) =>
                $q->where('is_active', $request->boolean('active'))
            )
            ->withCount('properties')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $users->items(),
            'meta'    => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'email'              => ['required', 'email', 'unique:users,email'],
            'password'           => ['required', Rules\Password::defaults()],
            'phone'              => ['nullable', 'string', 'max:30'],
            'is_platform_admin'  => ['sometimes', 'boolean'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            ...$validated,
            'password'          => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created.',
            'data'    => $user,
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['properties' => fn ($q) => $q->withPivot('role', 'is_active')]);
        $user->loadCount('properties');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'email'             => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_platform_admin' => ['sometimes', 'boolean'],
            'is_active'         => ['sometimes', 'boolean'],
            'password'          => ['sometimes', Rules\Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
            'data'    => $user->fresh(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted.',
        ]);
    }

    public function impersonate(User $user): JsonResponse
    {
        $token = $user->createToken('impersonation', ['*'], now()->addHours(1));

        return response()->json([
            'success' => true,
            'message' => "Impersonating {$user->name}.",
            'data'    => [
                'token'      => $token->plainTextToken,
                'expires_at' => now()->addHours(1)->toISOString(),
                'user'       => $user,
            ],
        ]);
    }
}
