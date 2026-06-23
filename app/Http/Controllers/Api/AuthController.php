<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Authentication", description: "API Endpoints for user authentication")]
class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    #[OA\Post(
        path: "/api/auth/register",
        summary: "Register a new user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "password", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "role", type: "string", enum: ["owner", "seeker"], example: "seeker")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 201, description: "User registered successfully"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'nullable|email|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:owner,seeker'
        ]);

        return response()->json(
            $this->authService->register($data),
            201
        );
    }

    #[OA\Post(
        path: "/api/auth/login",
        summary: "Login user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Login successful"),
            new OA\Response(response: 401, description: "Invalid credentials")
        ]
    )]
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'password' => 'required'
        ]);

        return response()->json(
            $this->authService->login($data)
        );
    }

    #[OA\Post(
        path: "/api/auth/refresh",
        summary: "Refresh access token",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["refresh_token"],
                properties: [
                    new OA\Property(property: "refresh_token", type: "string")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Token refreshed")
        ]
    )]
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required'
        ]);

        return response()->json(
            $this->authService->refresh($request->refresh_token)
        );
    }

    #[OA\Post(
        path: "/api/auth/logout",
        summary: "Logout user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Logged out successfully")
        ]
    )]
    public function logout(Request $request)
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    #[OA\Post(
        path: "/api/auth/logout-all",
        summary: "Logout from all devices",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Logged out from all devices")
        ]
    )]
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out from all devices']);
    }

    #[OA\Post(
        path: "/api/auth/forgot-password",
        summary: "Send password reset link",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Reset link sent")
        ]
    )]
    public function forgotPassword(Request $request)
    {
        \Log::info('début de la méthode forgotPassword pour l\'email: ' . $request->email);
        $request->validate(['email' => 'required|email']);

        \Log::info('email validé: ' . $request->email);

        Password::sendResetLink($request->only('email'));
        \Log::info('Password reset link sent to: ' . $request->email);

        return response()->json(['message' => 'Reset link sent']);
    }

    #[OA\Post(
        path: "/api/auth/reset-password",
        summary: "Reset password",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "token", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "token", type: "string"),
                    new OA\Property(property: "password", type: "string", format: "password"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Password reset successfully")
        ]
    )]
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        return response()->json(['message' => __($status)]);
    }

    #[OA\Get(
        path: "/api/auth/me",
        summary: "Get authenticated user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "User data")
        ]
    )]
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}