<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        if (!$user->is_active) {
            return ApiResponse::error('Account is inactive', null, 403);
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['student', 'coach']);

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && method_exists($user->currentAccessToken(), 'delete')) {
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['student', 'coach']);

        return ApiResponse::success(new UserResource($user), 'User profile retrieved successfully');
    }

    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['student', 'coach']);

        return ApiResponse::success([
            'user' => new UserResource($user),
        ], 'Authenticated');
    }
}
