<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChercheurService;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Chercheur', description: 'Fonctionnalités pour les chercheurs de logement')]
class ChercheurController extends Controller
{
    public function __construct(
        private ChercheurService $chercheurService,
        private PropertyService  $propertyService,
    ) {}

    #[OA\Post(
        path: '/api/chercheur/favoris/{propertyId}',
        summary: 'Ajouter ou retirer une annonce des favoris (toggle)',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        parameters: [
            new OA\Parameter(name: 'propertyId', in: 'path', required: true,
                description: 'ID de l\'annonce',
                schema: new OA\Schema(type: 'integer', example: 3)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Favori ajouté ou retiré'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function toggleFavori(Request $request, int $propertyId): JsonResponse
    {
        try {
            $result = $this->chercheurService->toggleFavori($request->user(), $propertyId);
            return $this->sendApiResponse($result, $result['message']);
        } catch (ValidationException $e) {
            return $this->sendApiResponse(null, 'Annonce introuvable.', false, 404);
        }
    }

    #[OA\Get(
        path: '/api/chercheur/favoris',
        summary: 'Mes annonces favorites',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des favoris')]
    )]
    public function mesFavoris(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $favoris = $this->chercheurService->mesFavoris($request->user(), $perPage);

        return $this->sendApiResponse(
            $favoris->through(fn($f) => [
                'id'        => $f->id,
                'property'  => $f->property
                    ? $this->propertyService->formatProperty($f->property)
                    : null,
                'ajouté_le' => $f->created_at->format('d/m/Y'),
            ]),
            'Favoris récupérés.'
        );
    }

    #[OA\Post(
        path: '/api/chercheur/visites',
        summary: 'Demander une visite pour une annonce',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_property', 'date_souhaitee'],
                properties: [
                    new OA\Property(property: 'id_property', type: 'integer', example: 5,
                        description: 'ID de l\'annonce à visiter'),
                    new OA\Property(property: 'date_souhaitee', type: 'string',
                        format: 'date-time', example: '2026-07-15 10:00:00',
                        description: 'Date et heure souhaitées pour la visite'),
                    new OA\Property(property: 'message', type: 'string', nullable: true,
                        example: 'Je suis disponible le matin de préférence.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Demande de visite envoyée'),
            new OA\Response(response: 422, description: 'Données invalides ou demande déjà existante'),
        ]
    )]
    public function demanderVisite(Request $request): JsonResponse
    {
        $request->validate([
            'id_property'    => 'required|integer|exists:properties,id',
            'date_souhaitee' => 'required|date|after:now',
            'message'        => 'sometimes|nullable|string|max:500',
        ]);

        try {
            $visite = $this->chercheurService->demanderVisite(
                $request->user(),
                $request->only(['id_property', 'date_souhaitee', 'message'])
            );
            return $this->sendApiResponse(
                $this->chercheurService->formatVisite($visite),
                'Demande de visite envoyée. Le propriétaire va vous contacter.',
                true,
                201
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Get(
        path: '/api/chercheur/visites',
        summary: 'Mes demandes de visite',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Mes demandes de visite')]
    )]
    public function mesVisites(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $visites = $this->chercheurService->mesVisites($request->user(), $perPage);

        return $this->sendApiResponse(
            $visites->through(fn($v) => $this->chercheurService->formatVisite($v)),
            'Visites récupérées.'
        );
    }

    #[OA\Patch(
        path: '/api/chercheur/visites/{id}/annuler',
        summary: 'Annuler une demande de visite',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Visite annulée'),
            new OA\Response(response: 422, description: 'Visite introuvable ou non annulable'),
        ]
    )]
    public function annulerVisite(Request $request, int $id): JsonResponse
    {
        try {
            $visite = $this->chercheurService->annulerVisite($request->user(), $id);
            return $this->sendApiResponse(
                $this->chercheurService->formatVisite($visite),
                'Demande annulée.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    // ── Côté propriétaire ─────────────────────────────────────────────

    #[OA\Get(
        path: '/api/owner/visites',
        summary: 'Visites reçues sur mes annonces (owner)',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Visites reçues'),
            new OA\Response(response: 403, description: 'Réservé aux propriétaires'),
        ]
    )]
    public function visitesRecues(Request $request): JsonResponse
    {
        if (!$request->user()->isOwner() && !$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Réservé aux propriétaires.', false, 403);
        }

        $perPage = (int) $request->input('per_page', 15);
        $visites = $this->chercheurService->visitesRecues($request->user(), $perPage);

        return $this->sendApiResponse(
            $visites->through(fn($v) => $this->chercheurService->formatVisite($v)),
            'Visites reçues.'
        );
    }

    #[OA\Patch(
        path: '/api/owner/visites/{id}/repondre',
        summary: 'Confirmer ou refuser une visite (owner)',
        security: [['sanctum' => []]],
        tags: ['Chercheur'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string',
                        enum: ['confirmée', 'refusée'], example: 'confirmée'),
                    new OA\Property(property: 'note_proprietaire', type: 'string',
                        nullable: true, example: 'Rendez-vous confirmé. Je vous attends à 10h.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Réponse envoyée'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Visite introuvable'),
        ]
    )]
    public function repondreVisite(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'            => 'required|in:confirmée,refusée',
            'note_proprietaire' => 'sometimes|nullable|string|max:500',
        ]);

        try {
            $visite = $this->chercheurService->repondreVisite(
                $request->user(), $id,
                $request->status,
                $request->note_proprietaire
            );
            return $this->sendApiResponse(
                $this->chercheurService->formatVisite($visite),
                'Réponse envoyée.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['visite']) && str_contains(current($errors['visite']), 'autorisé') ? 403 : 422;
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }
}
