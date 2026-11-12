<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required_without:username|nullable|email',
            'username' => 'required_without:email|nullable|string|max:255',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::query()
            ->when(
                filled($credentials['username'] ?? null),
                fn ($query) => $query->where(function ($query) use ($credentials): void {
                    $query->where('username', $credentials['username'])
                        ->orWhere('email', $credentials['username']);
                }),
                fn ($query) => $query->where('email', $credentials['email'])
            )
            ->first();

        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect or the account is unavailable.'],
            ]);
        }

        $token = $user->createToken($request->device_name ?? 'api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = 'customer';
        $validated['is_active'] = true;

        $user = User::create($validated);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request): JsonResponse
    {
        return (new UserResource($request->user()->load(['cart.items.product', 'orders', 'wishlists.product'])))->response();
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'remove_avatar' => 'sometimes|boolean',
        ]);

        $avatar = $request->file('avatar');
        $previousAvatar = $user->avatar;
        unset($validated['avatar']);
        $removeAvatar = $request->boolean('remove_avatar') && !$avatar;
        unset($validated['remove_avatar']);

        if ($avatar) {
            $path = $avatar->store("avatars/{$user->id}", 'public');
            $validated['avatar'] = $path;
        } elseif ($removeAvatar) {
            $validated['avatar'] = null;
        }

        $user->update($validated);

        if (($avatar || $removeAvatar) && $previousAvatar && str_starts_with($previousAvatar, "avatars/{$user->id}/")) {
            Storage::disk('public')->delete($previousAvatar);
        }

        return (new UserResource($user->refresh()))->response();
    }
}