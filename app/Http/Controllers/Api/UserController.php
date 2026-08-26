<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'User', description: 'Gestion du profil utilisateur')]
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    #[OA\Get(
        path: '/api/user/profile',
        summary: 'Mon profil',
        security: [['sanctum' => []]],
        tags: ['User'],
        responses: [new OA\Response(response: 200, description: 'Profil utilisateur')]
    )]
    public function profile(Request $request): JsonResponse
    {
        return $this->sendApiResponse(
            $this->userService->getProfile($request->user()),
            'Profil récupéré.'
        );
    }

    #[OA\Put(
        path: '/api/user/profile',
        summary: 'Mettre à jour mon profil',
        security: [['sanctum' => []]],
        tags: ['User'],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'phone', type: 'string'),
            new OA\Property(property: 'adress', type: 'string'),
            new OA\Property(property: 'preferences', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->validated());
        return $this->sendApiResponse($user, 'Profil mis à jour avec succès.');
    }

    #[OA\Post(
        path: '/api/user/avatar',
        summary: 'Upload avatar',
        security: [['sanctum' => []]],
        tags: ['User'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(properties: [new OA\Property(property: 'avatar', type: 'string', format: 'binary')])
        )),
        responses: [
            new OA\Response(response: 200, description: 'Avatar mis à jour'),
            new OA\Response(response: 422, description: 'Fichier invalide'),
        ]
    )]
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->userService->uploadAvatar($request->user(), $request->file('avatar'));
        return $this->sendApiResponse($user, 'Photo de profil mise à jour.');
    }

    #[OA\Delete(
        path: '/api/user/avatar',
        summary: 'Supprimer avatar',
        security: [['sanctum' => []]],
        tags: ['User'],
        responses: [new OA\Response(response: 200, description: 'Avatar supprimé')]
    )]
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->avatar) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }
        return $this->sendApiResponse(null, 'Photo de profil supprimée.');
    }

    #[OA\Delete(
        path: '/api/user/account',
        summary: 'Supprimer mon compte',
        security: [['sanctum' => []]],
        tags: ['User'],
        responses: [new OA\Response(response: 200, description: 'Compte supprimé')]
    )]
    public function deleteAccount(Request $request): JsonResponse
    {
        $this->userService->deleteAccount($request->user());
        return $this->sendApiResponse(null, 'Compte supprimé. Au revoir !');
    }

    #[OA\Post(
        path: '/api/user/restore/{id}',
        summary: 'Restaurer un compte supprimé (admin)',
        security: [['sanctum' => []]],
        tags: ['User'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Compte restauré'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Compte introuvable'),
        ]
    )]
    public function restoreAccount(Request $request, int $id): JsonResponse
    {
        // Seul un admin peut restaurer
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action non autorisée.', false, 403);
        }

        try {
            $user = $this->userService->restoreAccount($id);
            return $this->sendApiResponse($user, 'Compte restauré avec succès.');
        } catch (ValidationException $e) {
            return $this->sendApiResponse(['errors' => $e->errors()], 'Impossible de restaurer ce compte.', false, 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->sendApiResponse(null, 'Compte introuvable.', false, 404);
        }
    }

    #[OA\Put(
        path: '/api/user/password',
        summary: 'Changer mon mot de passe',
        security: [['sanctum' => []]],
        tags: ['User'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe mis à jour'),
            new OA\Response(response: 422, description: 'Mot de passe actuel incorrect ou validation échouée'),
        ]
    )]
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.required'         => 'Le nouveau mot de passe est obligatoire.',
            'password.min'              => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'        => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return $this->sendApiResponse(
                ['errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']]],
                'Le mot de passe actuel est incorrect.',
                false,
                422
            );
        }

        $user->update([
            'password'   => Hash::make($request->input('password')),
            'updated_by' => $user->id,
        ]);

        return $this->sendApiResponse(null, 'Mot de passe mis à jour avec succès.');
    }
}
