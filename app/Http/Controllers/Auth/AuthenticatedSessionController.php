<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    use ApiResponseTrait;


    /**
     * Handle an incoming authentication request for API.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                return $this->apiError('Invalid credentials', null, 401);
            }

            $user = Auth::user();
            $token = $user->createToken('api-token')->plainTextToken;

            return $this->apiSuccess([
                'user' => $user,
                'token' => $token
            ], 'Login successful');

        } catch (ValidationException $e) {
            return $this->apiError('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Destroy an authenticated session for API.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->apiSuccess(null, 'Logged out successfully');
    }
}