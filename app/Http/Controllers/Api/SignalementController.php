<?php

namespace App\Http\Controllers\Api;

use App\Enums\SignalementStatus;
use App\Enums\SignalementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signalement\StoreSignalementRequest;
use App\Services\SignalementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Signalements', description: 'Signalements d\'abus ou de fraudes')]
class SignalementController extends Controller
{
    public function __construct(private SignalementService $signalementService) {}

    #[OA\Post(
        path: '/api/signalements',
        summary: 'Faire un signalement',
        security: [['sanctum' => []]],
        tags: ['Signalements'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['motif', 'description', 'type_signalement'],
            properties: [
                new OA\Property(property: 'motif', type: 'string', example: 'Annonce frauduleuse'),
                new OA\Property(property: 'description', type: 'string', example: 'Les photos ne correspondent pas à la réalité...'),
                new OA\Property(property: 'type_signalement', type: 'string', enum: SignalementType::class),
                new OA\Property(property: 'id_property', type: 'integer', nullable: true, description: 'ID de l\'annonce signalée'),
                new OA\Property(property: 'id_user_signale', type: 'integer', nullable: true, description: 'ID de l\'utilisateur signalé'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Signalement envoyé'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function store(StoreSignalementRequest $request): JsonResponse
    {
        $signalement = $this->signalementService->store($request->user(), $request->validated());

        return $this->sendApiResponse(
            $this->signalementService->formatSignalement($signalement),
            'Signalement envoyé. Notre équipe va l\'examiner sous 48h.',
            true,
            201
        );
    }

    #[OA\Get(
        path: '/api/admin/signalements',
        summary: 'Tous les signalements (admin)',
        security: [['sanctum' => []]],
        tags: ['Signalements'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: SignalementStatus::class)),
            new OA\Parameter(name: 'type_signalement', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: SignalementType::class)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des signalements'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $filters = $request->only(['status', 'type_signalement']);
        $perPage = (int) $request->input('per_page', 15);
        $signalements = $this->signalementService->allSignalements($filters, $perPage);

        return $this->sendApiResponse(
            $signalements->through(fn($s) => $this->signalementService->formatSignalement($s)),
            'Signalements récupérés.'
        );
    }

    #[OA\Patch(
        path: '/api/admin/signalements/{id}/treat',
        summary: 'Traiter un signalement (admin)',
        security: [['sanctum' => []]],
        tags: ['Signalements'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: SignalementStatus::class),
                new OA\Property(property: 'note_admin', type: 'string', nullable: true, example: 'Annonce supprimée suite à vérification.'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Signalement traité'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ]
    )]
    public function treat(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $request->validate([
            'status'     => ['required', SignalementStatus::rule()],
            'note_admin' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        try {
            $signalement = $this->signalementService->treat(
                $request->user(), $id,
                $request->status,
                $request->note_admin
            );

            return $this->sendApiResponse(
                $this->signalementService->formatSignalement($signalement),
                'Signalement traité avec succès.'
            );
        } catch (ValidationException $e) {
            return $this->sendApiResponse(null, 'Signalement introuvable.', false, 404);
        }
    }
}