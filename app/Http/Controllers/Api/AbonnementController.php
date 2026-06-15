<?php

namespace App\Http\Controllers\Api;

use App\Enums\AbonnementType;
use App\Http\Controllers\Controller;
use App\Services\AbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Abonnements', description: 'Gestion des abonnements propriétaires')]
class AbonnementController extends Controller
{
    public function __construct(private AbonnementService $abonnementService) {}

    #[OA\Get(
        path: '/api/abonnements/tarifs',
        summary: 'Liste des tarifs d\'abonnement disponibles (public)',
        tags: ['Abonnements'],
        responses: [new OA\Response(response: 200, description: 'Tarifs disponibles')]
    )]
    public function tarifs(): JsonResponse
    {
        return $this->sendApiResponse(
            $this->abonnementService->getTarifs(),
            'Tarifs récupérés.'
        );
    }

    #[OA\Get(
        path: '/api/abonnements/actif',
        summary: 'Mon abonnement actif',
        security: [['sanctum' => []]],
        tags: ['Abonnements'],
        responses: [
            new OA\Response(response: 200, description: 'Abonnement actif ou null'),
        ]
    )]
    public function actif(Request $request): JsonResponse
    {
        $abonnement = $this->abonnementService->getAbonnementActif($request->user());

        return $this->sendApiResponse(
            $abonnement ? $this->abonnementService->formatAbonnement($abonnement) : null,
            $abonnement ? 'Abonnement actif trouvé.' : 'Aucun abonnement actif.'
        );
    }

    #[OA\Get(
        path: '/api/abonnements/historique',
        summary: 'Historique de mes abonnements',
        security: [['sanctum' => []]],
        tags: ['Abonnements'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Historique des abonnements')]
    )]
    public function historique(Request $request): JsonResponse
    {
        $perPage     = (int) $request->input('per_page', 15);
        $abonnements = $this->abonnementService->historique($request->user(), $perPage);

        return $this->sendApiResponse(
            $abonnements->through(fn($a) => $this->abonnementService->formatAbonnement($a)),
            'Historique récupéré.'
        );
    }

    #[OA\Post(
        path: '/api/abonnements/initier',
        summary: 'Initier un abonnement (étape 1 — avant paiement KKiaPay)',
        description: 'Crée un abonnement en attente et retourne les informations nécessaires au SDK KKiaPay côté frontend.',
        security: [['sanctum' => []]],
        tags: ['Abonnements'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: AbonnementType::class,
                        example: 'mensuel',
                        description: 'Type d\'abonnement souhaité'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200,
                description: 'Abonnement initié. Utilisez kkiapay_public_key + montant pour payer via le SDK KKiaPay.'),
            new OA\Response(response: 422, description: 'Abonnement déjà actif ou type invalide'),
        ]
    )]
    public function initier(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', AbonnementType::rule()],
        ]);

        if (!$request->user()->isOwner() && !$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Les abonnements sont réservés aux propriétaires.', false, 403);
        }

        try {
            $result = $this->abonnementService->initier($request->user(), $request->type);
            return $this->sendApiResponse($result, 'Abonnement initié. Procédez au paiement KKiaPay.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Post(
        path: '/api/abonnements/confirmer',
        summary: 'Confirmer un abonnement après paiement KKiaPay (étape 2)',
        description: 'À appeler après que le SDK KKiaPay a retourné un transaction_id. Le backend vérifie la transaction auprès de KKiaPay et active l\'abonnement.',
        security: [['sanctum' => []]],
        tags: ['Abonnements'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['paiement_id', 'transaction_id'],
                properties: [
                    new OA\Property(property: 'paiement_id', type: 'integer', example: 5,
                        description: 'ID du paiement retourné par /api/abonnements/initier'),
                    new OA\Property(property: 'transaction_id', type: 'string',
                        example: 'kkp_txn_abc123def456',
                        description: 'Transaction ID retourné par le SDK KKiaPay après paiement réussi'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Abonnement activé avec succès'),
            new OA\Response(response: 422, description: 'Paiement invalide ou transaction KKiaPay non confirmée'),
        ]
    )]
    public function confirmer(Request $request): JsonResponse
    {
        $request->validate([
            'paiement_id'    => 'required|integer',
            'transaction_id' => 'required|string',
        ]);

        try {
            $result = $this->abonnementService->confirmer(
                $request->user(),
                $request->paiement_id,
                $request->transaction_id
            );
            return $this->sendApiResponse($result, $result['message']);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }
}