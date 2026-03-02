<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PropertyUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');

        $users = $property->users()
            ->withPivot(['role', 'permissions', 'is_active', 'invited_at', 'accepted_at'])
            ->orderByPivot('role')
            ->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'phone'       => $u->phone,
                'avatar_url'  => $u->avatar_url,
                'role'        => $u->pivot->role,
                'is_active'   => $u->pivot->is_active,
                'invited_at'  => $u->pivot->invited_at,
                'accepted_at' => $u->pivot->accepted_at,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $property  = $request->get('current_property');
        $this->authorize('manageUsers', $property);

        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'name'     => ['required', 'string', 'max:255'],
            'role'     => ['required', Rule::in(['manager', 'receptionist', 'accountant', 'housekeeping'])],
        ]);

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name'     => $validated['name'],
                'password' => Hash::make(\Illuminate\Support\Str::random(16)),
                'is_active'=> true,
            ]
        );

        // Check if already attached
        if ($property->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this property.',
            ], 422);
        }

        $property->users()->attach($user->id, [
            'role'       => $validated['role'],
            'is_active'  => true,
            'invited_at' => now(),
        ]);

        // TODO: Send invite email

        return response()->json([
            'success' => true,
            'message' => "Invitation sent to {$user->email}.",
            'data'    => [
                'user_id' => $user->id,
                'email'   => $user->email,
                'role'    => $validated['role'],
            ],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $property = $request->get('current_property');
        $this->authorize('manageUsers', $property);

        $validated = $request->validate([
            'role'      => ['sometimes', Rule::in(['owner', 'manager', 'receptionist', 'accountant', 'housekeeping'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $property->users()->updateExistingPivot($user->id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
        ]);
    }

    public function remove(Request $request, User $user): JsonResponse
    {
        $property = $request->get('current_property');
        $this->authorize('manageUsers', $property);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove yourself.',
            ], 422);
        }

        $property->users()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'User removed from property.',
        ]);
    }
}
