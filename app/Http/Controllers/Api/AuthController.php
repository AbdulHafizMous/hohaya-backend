<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Notifications\VerifyEmailNotification;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Authentication', description: 'Endpoints d\'authentification')]
class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    #[OA\Post(path: '/api/auth/register', summary: 'Inscription', tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'password', 'password_confirmation', 'role'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Moustapha Diallo'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@email.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+22997000000'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                new OA\Property(property: 'role', type: 'string', enum: ['owner', 'seeker'], example: 'seeker'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());
            return $this->sendApiResponse($result, 'Compte créé avec succès.', true, 201);
        } catch (\Exception $e) {
            return $this->sendApiResponse(null, $e->getMessage(), false, 500);
        }
    }

    #[OA\Post(path: '/api/auth/login', summary: 'Connexion', tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@email.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+22997000000'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Connexion réussie'),
            new OA\Response(response: 401, description: 'Identifiants incorrects'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());
            return $this->sendApiResponse($result, 'Connexion réussie.');
        } catch (ValidationException $e) {
            return $this->sendApiResponse(
                ['errors' => $e->errors()],
                'Identifiants incorrects.',
                false,
                401
            );
        } catch (\Exception $e) {
            return $this->sendApiResponse(null, $e->getMessage(), false, 500);
        }
    }

    #[OA\Post(path: '/api/auth/refresh', summary: 'Renouveler le token', tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [new OA\Property(property: 'refresh_token', type: 'string')]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Token renouvelé'),
            new OA\Response(response: 401, description: 'Token invalide'),
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        try {
            $result = $this->authService->refresh($request->refresh_token);
            return $this->sendApiResponse($result, 'Token renouvelé.');
        } catch (ValidationException $e) {
            return $this->sendApiResponse(
                ['errors' => $e->errors()],
                'Refresh token invalide ou expiré.',
                false,
                401
            );
        }
    }

    #[OA\Post(path: '/api/auth/logout', summary: 'Déconnexion', security: [['sanctum' => []]], tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'Déconnecté')]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return $this->sendApiResponse(null, 'Déconnecté avec succès.');
    }

    #[OA\Post(path: '/api/auth/logout-all', summary: 'Déconnexion tous appareils', security: [['sanctum' => []]], tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'Tous déconnectés')]
    )]
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return $this->sendApiResponse(null, 'Tous les appareils ont été déconnectés.');
    }

    #[OA\Post(path: '/api/auth/forgot-password', summary: 'Mot de passe oublié', tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(property: 'email', type: 'string', format: 'email')]
        )),
        responses: [new OA\Response(response: 200, description: 'Lien envoyé')]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        \Log::info('début de la méthode forgotPassword pour l\'email: ' . $request->email);
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));
        // Toujours 200 pour éviter l'énumération d'emails
        return $this->sendApiResponse(null, 'Si cet email existe, un lien a été envoyé.');
    }

    #[OA\Post(path: '/api/auth/reset-password', summary: 'Réinitialiser le mot de passe', tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'token', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe réinitialisé'),
            new OA\Response(response: 422, description: 'Token invalide'),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->sendApiResponse(null, __($status), false, 422);
        }

        return $this->sendApiResponse(null, 'Mot de passe réinitialisé avec succès.');
    }

    #[OA\Get(path: '/api/auth/me', summary: 'Profil connecté', security: [['sanctum' => []]], tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'Données utilisateur')]
    )]
    public function me(Request $request): JsonResponse
    {
        return $this->sendApiResponse(
            $this->authService->formatUser($request->user()),
            'Utilisateur récupéré.'
        );
    }

    #[OA\Get(path: '/api/auth/verify-email/{id}/{hash}', summary: 'Vérifier email', tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Email vérifié')]
    )]
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = \App\Models\User::findOrFail($id);

        if (!hash_equals(sha1($user->email), $hash)) {
            return $this->sendApiResponse(null, 'Lien de vérification invalide.', false, 403);
        }

        if (!$request->hasValidSignature()) {
            return $this->sendApiResponse(null, 'Lien de vérification expiré.', false, 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->sendApiResponse(null, 'Email déjà vérifié.');
        }

        $user->markEmailAsVerified();
        return $this->sendApiResponse(null, 'Email vérifié avec succès. Bienvenue sur Hohaya !');
    }

    #[OA\Post(path: '/api/auth/resend-verification', summary: 'Renvoyer email vérification', security: [['sanctum' => []]], tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'Email renvoyé')]
    )]
    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->sendApiResponse(null, 'Email déjà vérifié.');
        }

        $request->user()->notify(new VerifyEmailNotification());
        return $this->sendApiResponse(null, 'Email de vérification renvoyé.');
    }
}