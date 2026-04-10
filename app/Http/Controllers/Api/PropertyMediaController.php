<?php

namespace App\Http\Controllers\Api;

use App\Enums\MediaType;
use App\Enums\PropertyZone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\UploadMediasRequest;
use App\Services\PropertyMediaService;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Property Medias', description: 'Gestion des médias (photos et vidéos) des annonces')]
class PropertyMediaController extends Controller
{
    public function __construct(
        private PropertyService      $propertyService,
        private PropertyMediaService $mediaService
    ) {}

    #[OA\Post(
        path: '/api/properties/{id}/medias',
        summary: 'Uploader des médias sur une annonce (images et/ou vidéos)',
        description: 'Utilisé à la création et à la modification. Envoyer les fichiers en multipart/form-data avec les métadonnées associées.',
        security: [['sanctum' => []]],
        tags: ['Property Medias'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'annonce',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['fichier', 'type', 'zone'],
                    properties: [
                        new OA\Property(
                            property: 'fichier',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier image ou vidéo'
                        ),
                        new OA\Property(
                            property: 'type',
                            type: 'string',
                            enum: MediaType::class,
                            example: 'image'
                        ),
                        new OA\Property(
                            property: 'zone',
                            type: 'string',
                            enum: PropertyZone::class,
                            example: 'salon'
                        ),
                        new OA\Property(
                            property: 'description',
                            type: 'string',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'is_principale',
                            type: 'boolean',
                            default: false,
                            nullable: true
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Médias uploadés avec succès'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Fichier invalide ou quota dépassé'),
        ]
    )]
    public function upload(UploadMediasRequest $request, int $id): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);

            // Le tableau mediaData contient un seul média ici
            $mediaData = [
                [
                    'fichier'        => $request->file('fichier'),
                    'type'           => $request->input('type'),
                    'zone'           => $request->input('zone'),
                    'description'    => $request->input('description'),
                    'is_principale'  => $request->boolean('is_principale'),
                ]
            ];

            $property = $this->mediaService->upload(
                $request->user(),
                $property,
                $mediaData
            );

            return $this->sendApiResponse(
                $this->formatPropertyWithMedias($property),
                'Média uploadé avec succès.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : 422;

            return $this->sendApiResponse(
                ['errors' => $errors],
                current($errors)[0] ?? 'Erreur de validation',
                false,
                $status
            );
        } catch (\Exception $e) {
            return $this->sendApiResponse(
                null,
                'Une erreur est survenue lors de l\'upload : ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/properties/{id}/medias',
        summary: 'Lister tous les médias d\'une annonce',
        tags: ['Property Medias'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'type',
                in: 'query',
                required: false,
                description: 'Filtrer par type',
                schema: new OA\Schema(type: 'string', enum: MediaType::class)
            ),
            new OA\Parameter(
                name: 'zone',
                in: 'query',
                required: false,
                description: 'Filtrer par zone',
                schema: new OA\Schema(type: 'string', enum: PropertyZone::class)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des médias'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);
            $query    = $property->medias();

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('zone')) {
                $query->where('zone', $request->zone);
            }

            $medias = $query->get();

            return $this->sendApiResponse(
                $medias->map(fn($m) => $this->mediaService->formatMedia($m))->values()->toArray(),
                'Médias récupérés.'
            );
        } catch (ValidationException $e) {
            return $this->sendApiResponse(null, 'Annonce introuvable.', false, 404);
        }
    }

    #[OA\Delete(
        path: '/api/properties/{id}/medias/{mediaId}',
        summary: 'Supprimer un média d\'une annonce',
        security: [['sanctum' => []]],
        tags: ['Property Medias'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'mediaId',
                in: 'path',
                required: true,
                description: 'ID du média à supprimer',
                schema: new OA\Schema(type: 'integer', example: 3)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Média supprimé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Impossible — au moins une photo requise'),
        ]
    )]
    public function destroy(Request $request, int $id, int $mediaId): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);
            $property = $this->mediaService->delete($request->user(), $property, $mediaId);

            return $this->sendApiResponse(
                $this->formatPropertyWithMedias($property),
                'Média supprimé avec succès.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : 422;
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }

    #[OA\Post(
        path: '/api/properties/{id}/medias/{mediaId}/principal',
        summary: 'Définir un média comme photo principale de l\'annonce',
        security: [['sanctum' => []]],
        tags: ['Property Medias'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'mediaId',
                in: 'path',
                required: true,
                description: 'ID de l\'image à définir comme principale (doit être de type image)',
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo principale mise à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Média introuvable ou n\'est pas une image'),
        ]
    )]
    public function setPrincipal(Request $request, int $id, int $mediaId): JsonResponse
    {
        try {
            $property = $this->propertyService->show($id);
            $property = $this->mediaService->setPrincipal($request->user(), $property, $mediaId);

            return $this->sendApiResponse(
                $this->formatPropertyWithMedias($property),
                'Photo principale mise à jour.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : 422;
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }

    #[OA\Post(
        path: '/api/properties/{id}/medias/reorder',
        summary: 'Réordonner les médias d\'une annonce',
        security: [['sanctum' => []]],
        tags: ['Property Medias'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ordered_ids'],
                properties: [
                    new OA\Property(
                        property: 'ordered_ids',
                        type: 'array',
                        description: 'IDs des médias dans le nouvel ordre souhaité',
                        items: new OA\Items(type: 'integer'),
                        example: [3, 1, 4, 2]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Médias réordonnés'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function reorder(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'ordered_ids'   => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        try {
            $property = $this->propertyService->show($id);
            $property = $this->mediaService->reorder($request->user(), $property, $request->ordered_ids);

            return $this->sendApiResponse(
                $this->formatPropertyWithMedias($property),
                'Médias réordonnés.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $status = isset($errors['property']) ? 403 : 422;
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, $status);
        }
    }

    // ── Helper privé ─────────────────────────────────────────────────

    private function formatPropertyWithMedias(\App\Models\Property $property): array
    {
        $medias  = $property->medias ?? collect();
        $images  = $medias->where('type', 'image')->values();
        $videos  = $medias->where('type', 'video')->values();
        $principale = $medias->firstWhere('is_principale', true);

        return [
            'id'              => $property->id,
            'title'           => $property->title,
            'media_principal' => $principale
                ? $this->mediaService->formatMedia($principale)
                : null,
            'images'          => $images->map(fn($m) => $this->mediaService->formatMedia($m))->toArray(),
            'videos'          => $videos->map(fn($m) => $this->mediaService->formatMedia($m))->toArray(),
            'total_medias'    => $medias->count(),
        ];
    }
}
