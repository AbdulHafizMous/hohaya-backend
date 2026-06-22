<?php

namespace App\Http\Controllers\Api;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Properties', description: 'Gestion des annonces immobilières')]
class PropertyController extends Controller
{
    public function __construct(private PropertyService $propertyService) {}

    #[OA\Get(
        path: '/api/properties',
        summary: 'Liste des annonces disponibles (public)',
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'ville', in: 'query', required: false,
                description: 'Filtrer par ville (ex: Cotonou)',
                schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'quartier', in: 'query', required: false,
                description: 'Filtrer par quartier',
                schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'commune', in: 'query', required: false,
                description: 'Filtrer par commune',
                schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type_logement', in: 'query', required: false,
                description: 'Type de logement',
                schema: new OA\Schema(type: 'string', enum: PropertyType::class)),
            new OA\Parameter(name: 'prix_min', in: 'query', required: false,
                description: 'Prix minimum (XOF)',
                schema: new OA\Schema(type: 'number', example: 50000)),
            new OA\Parameter(name: 'prix_max', in: 'query', required: false,
                description: 'Prix maximum (XOF)',
                schema: new OA\Schema(type: 'number', example: 200000)),
            new OA\Parameter(name: 'nb_pieces', in: 'query', required: false,
                description: 'Nombre de pièces',
                schema: new OA\Schema(type: 'integer', example: 3)),
            new OA\Parameter(name: 'meuble', in: 'query', required: false,
                description: 'Meublé ou non',
                schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                description: 'Résultats par page',
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des annonces')]
    )]
    public function index(Request $request): JsonResponse
    {
        $filters    = $request->only(['ville', 'quartier', 'commune', 'type_logement', 'prix_min', 'prix_max', 'nb_pieces', 'meuble']);
        $perPage    = (int) $request->input('per_page', 15);
        $properties = $this->propertyService->list($filters, $perPage);

        return $this->sendApiResponse(
            $properties->through(fn($p) => $this->propertyService->formatProperty($p)),
            'Annonces récupérées.'
        );
    }

    #[OA\Get(
        path: '/api/properties/{id}',
        summary: 'Détail d\'une annonce (public)',
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                description: 'ID de l\'annonce',
                schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de l\'annonce'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);
            return $this->sendApiResponse(
                $this->propertyService->formatProperty($property),
                'Annonce récupérée.'
            );
        } catch (ValidationException $e) {
            return $this->sendApiResponse(null, 'Annonce introuvable.', false, 404);
        }
    }

    #[OA\Get(
        path: '/api/properties/my',
        summary: 'Mes annonces (owner uniquement)',
        security: [['sanctum' => []]],
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mes annonces'),
            new OA\Response(response: 403, description: 'Réservé aux propriétaires'),
        ]
    )]
    public function myProperties(Request $request): JsonResponse
    {
        if (!$request->user()->isOwner() && !$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Réservé aux propriétaires.', false, 403);
        }

        $perPage    = (int) $request->input('per_page', 15);
        $properties = $this->propertyService->myProperties($request->user(), $perPage);

        return $this->sendApiResponse(
            $properties->through(fn($p) => $this->propertyService->formatProperty($p)),
            'Mes annonces récupérées.'
        );
    }

    #[OA\Post(
        path: '/api/properties',
        summary: 'Créer une annonce (owner) — sans médias',
        description: 'Crée l\'annonce. Uploadez ensuite les médias via POST /api/properties/{id}/medias',
        security: [['sanctum' => []]],
        tags: ['Properties'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'description', 'quartier', 'ville', 'prix_loyer', 'type_logement', 'condition', 'nb_pieces'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Beau appartement à Cadjehoun',
                        description: 'Titre de l\'annonce'),
                    new OA\Property(property: 'description', type: 'string',
                        example: 'Appartement moderne avec toutes commodités...',
                        description: 'Description détaillée du logement'),
                    new OA\Property(property: 'indications_acces', type: 'string', nullable: true,
                        example: 'Après le carrefour Total, 2ème rue à droite',
                        description: 'Indications pour trouver le logement (pas toujours d\'adresse précise)'),
                    new OA\Property(property: 'quartier', type: 'string', example: 'Cadjehoun'),
                    new OA\Property(property: 'commune', type: 'string', nullable: true, example: 'Littoral'),
                    new OA\Property(property: 'ville', type: 'string', example: 'Cotonou'),
                    new OA\Property(property: 'pays', type: 'string', example: 'Bénin',
                        description: 'Pays (défaut: Bénin)'),
                    new OA\Property(property: 'prix_loyer', type: 'number', example: 75000,
                        description: 'Prix du loyer mensuel en XOF'),
                    new OA\Property(property: 'devise', type: 'string', example: 'XOF',
                        description: 'Devise (défaut: XOF — Franc CFA)'),
                    new OA\Property(property: 'type_logement', type: 'string',
                        enum: PropertyType::class, example: 'appartement'),
                    new OA\Property(property: 'condition', type: 'string', example: 'Bon état',
                        description: 'État général du logement'),
                    new OA\Property(property: 'nb_avance', type: 'integer', example: 3,
                        description: 'Nombre de mois d\'avance requis'),
                    new OA\Property(property: 'caution_electricite', type: 'number', nullable: true,
                        example: 15000, description: 'Caution électricité (XOF)'),
                    new OA\Property(property: 'caution_eau', type: 'number', nullable: true,
                        example: 10000, description: 'Caution eau (XOF)'),
                    new OA\Property(property: 'nb_pieces', type: 'integer', example: 3),
                    new OA\Property(property: 'date_debut_louer', type: 'string', format: 'date',
                        nullable: true, example: '2026-05-01', description: 'Date de disponibilité'),
                    new OA\Property(property: 'eau_courante', type: 'boolean', example: true),
                    new OA\Property(property: 'electricite', type: 'boolean', example: true),
                    new OA\Property(property: 'gardien', type: 'boolean', example: false),
                    new OA\Property(property: 'parking', type: 'boolean', example: false),
                    new OA\Property(property: 'meuble', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201,
                description: 'Annonce créée. Uploadez les médias via POST /api/properties/{id}/medias'),
            new OA\Response(response: 403, description: 'Non autorisé — compte non propriétaire ou non vérifié'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(StorePropertyRequest $request): JsonResponse
    {
        if (!$request->user()->isOwner() && !$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Seuls les propriétaires peuvent publier des annonces.', false, 403);
        }

        try {
            $property = $this->propertyService->store($request->user(), $request->validated());

            return $this->sendApiResponse(
                $this->propertyService->formatProperty($property),
                'Annonce créée. Uploadez maintenant vos photos via POST /api/properties/' . $property->id . '/medias',
                true,
                201
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Put(
        path: '/api/properties/{id}',
        summary: 'Modifier les infos d\'une annonce (sans médias)',
        security: [['sanctum' => []]],
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                description: 'ID de l\'annonce',
                schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Champs à modifier (tous optionnels)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Appartement rénové à Cadjehoun'),
                    new OA\Property(property: 'description', type: 'string', example: 'Description mise à jour...'),
                    new OA\Property(property: 'indications_acces', type: 'string', nullable: true),
                    new OA\Property(property: 'quartier', type: 'string', example: 'Cadjehoun'),
                    new OA\Property(property: 'commune', type: 'string', nullable: true),
                    new OA\Property(property: 'ville', type: 'string', example: 'Cotonou'),
                    new OA\Property(property: 'pays', type: 'string', example: 'Bénin'),
                    new OA\Property(property: 'prix_loyer', type: 'number', example: 80000),
                    new OA\Property(property: 'devise', type: 'string', example: 'XOF'),
                    new OA\Property(property: 'type_logement', type: 'string', enum: PropertyType::class),
                    new OA\Property(property: 'condition', type: 'string', example: 'Très bon état'),
                    new OA\Property(property: 'nb_avance', type: 'integer', example: 2),
                    new OA\Property(property: 'caution_electricite', type: 'number', nullable: true),
                    new OA\Property(property: 'caution_eau', type: 'number', nullable: true),
                    new OA\Property(property: 'nb_pieces', type: 'integer', example: 4),
                    new OA\Property(property: 'date_debut_louer', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'eau_courante', type: 'boolean'),
                    new OA\Property(property: 'electricite', type: 'boolean'),
                    new OA\Property(property: 'gardien', type: 'boolean'),
                    new OA\Property(property: 'parking', type: 'boolean'),
                    new OA\Property(property: 'meuble', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Annonce mise à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function update(UpdatePropertyRequest $request, int $id): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);
            $updated  = $this->propertyService->update($request->user(), $property, $request->validated());

            return $this->sendApiResponse(
                $this->propertyService->formatProperty($updated),
                'Annonce mise à jour.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : (isset($errors['id']) ? 404 : 422);
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }

    #[OA\Delete(
        path: '/api/properties/{id}',
        summary: 'Supprimer une annonce et tous ses médias (soft delete)',
        security: [['sanctum' => []]],
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                description: 'ID de l\'annonce',
                schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Annonce supprimée'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);
            $this->propertyService->destroy($request->user(), $property);
            return $this->sendApiResponse(null, 'Annonce et médias supprimés avec succès.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : 404;
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }

    #[OA\Patch(
        path: '/api/properties/{id}/status',
        summary: 'Changer le statut d\'une annonce (owner)',
        security: [['sanctum' => []]],
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string',
                        enum: PropertyStatus::class,
                        example: 'loué',
                        description: 'Nouveau statut de l\'annonce'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ]
    )]
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', PropertyStatus::rule()],
        ]);

        try {
            $property = $this->propertyService->show($id);
            $updated  = $this->propertyService->changeStatus($request->user(), $property, $request->status);
            return $this->sendApiResponse(
                $this->propertyService->formatProperty($updated),
                'Statut mis à jour.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : 422;
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }

    #[OA\Patch(
        path: '/api/properties/{id}/verify',
        summary: 'Vérifier ou suspendre une annonce (admin uniquement)',
        security: [['sanctum' => []]],
        tags: ['Properties'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_verified'],
                properties: [
                    new OA\Property(property: 'is_verified', type: 'boolean', example: true,
                        description: 'true = publier, false = suspendre'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Annonce vérifiée ou suspendue'),
            new OA\Response(response: 403, description: 'Réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function verify(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $request->validate(['is_verified' => 'required|boolean']);

        try {
            $property = $this->propertyService->show($id);
            $updated  = $this->propertyService->verify($property, $request->boolean('is_verified'));
            $msg      = $request->boolean('is_verified') ? 'Annonce vérifiée et publiée.' : 'Annonce suspendue.';
            return $this->sendApiResponse($this->propertyService->formatProperty($updated), $msg);
        } catch (ValidationException $e) {
            return $this->sendApiResponse(null, 'Annonce introuvable.', false, 404);
        }
    }
}