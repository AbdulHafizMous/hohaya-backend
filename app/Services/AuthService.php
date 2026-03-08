<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data)
    {
        $user = User::create($data);

        return $this->generateTokens($user);
    }


    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login user",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="email", type="string", example="user@email.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful"
     *     )
     * )
     */
    public function login(array $data)
    {
        $user = User::where('email', $data['email'] ?? null)
            ->orWhere('phone', $data['phone'] ?? null)
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'message' => ['Invalid credentials'],
            ]);
        }

        return $this->generateTokens($user);
    }

    public function refresh($refreshToken)
    {
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($refreshToken);

        if (!$token || !$token->can('refresh')) {
            throw ValidationException::withMessages([
                'message' => ['Invalid refresh token'],
            ]);
        }

        $user = $token->tokenable;

        $token->delete(); // rotation

        return $this->generateTokens($user);
    }

    private function generateTokens(User $user)
    {
        $user->tokens()->where('name', 'access-token')->delete();

        $accessToken = $user->createToken(
            'access-token',
            ['access'],
            now()->addMinutes(15)
        )->plainTextToken;

        $refreshToken = $user->createToken(
            'refresh-token',
            ['refresh'],
            now()->addDays(7)
        )->plainTextToken;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 900,
            'user' => $user
        ];
    }
}