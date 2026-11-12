<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'role' => 'sometimes|in:customer,admin',
            'is_active' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        $query = User::query();

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $term = $filters['search'];
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('username', 'like', "%{$term}%");
            });
        }

        $users = $query->latest()->paginate($filters['per_page'] ?? 15);

        return UserResource::collection($users)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:customer,admin',
            'is_active' => 'sometimes|boolean',
            'avatar' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        return (new UserResource($user->load(['cart.items.product', 'orders', 'wishlists.product'])))->response();
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'sometimes|in:customer,admin',
            'is_active' => 'sometimes|boolean',
            'avatar' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($user->is($request->user())) {
            abort_if(
                (array_key_exists('is_active', $validated) && !$validated['is_active'])
                || (array_key_exists('role', $validated) && $validated['role'] !== 'admin'),
                422,
                'You cannot deactivate or remove administrator access from your own account.'
            );
        }

        $removesAdminAccess = $user->isAdmin()
            && ((array_key_exists('is_active', $validated) && !$validated['is_active'])
                || (array_key_exists('role', $validated) && $validated['role'] !== 'admin'));
        if ($removesAdminAccess && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            abort(422, 'At least one active administrator account must remain.');
        }

        $user->update($validated);

        if (array_key_exists('is_active', $validated) && !$validated['is_active']) {
            $user->tokens()->delete();
        }

        return (new UserResource($user))->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        abort_if($user->is($request->user()), 422, 'You cannot delete your own account.');
        if ($user->isAdmin() && $user->is_active && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            abort(422, 'At least one active administrator account must remain.');
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admin only');
    }
}