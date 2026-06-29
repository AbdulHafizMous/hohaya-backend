<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Services\AbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Paiements', description: 'Historique et gestion des paiements')]
class PaiementController extends Controller
{
    public function __construct(private AbonnementService $abonnementService) {}

    #[OA\Get(
        path: '/api/paiements',
        summary: 'Mon historique de paiements',
        security: [['sanctum' => []]],
        tags: ['Paiements'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Historique des paiements')]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage  = (int) $request->input('per_page', 15);
        $paiements = Paiement::where('id_user', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return $this->sendApiResponse(
            $paiements->through(fn($p) => $this->abonnementService->formatPaiement($p)),
            'Paiements récupérés.'
        );
    }

    #[OA\Get(
        path: '/api/admin/paiements',
        summary: 'Tous les paiements (admin)',
        security: [['sanctum' => []]],
        tags: ['Paiements'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false,
                schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false,
                schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tous les paiements'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $query = Paiement::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $paiements = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendApiResponse(
            $paiements->through(fn($p) => array_merge(
                $this->abonnementService->formatPaiement($p),
                ['user' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name, 'email' => $p->user->email] : null]
            )),
            'Paiements récupérés.'
        );
    }
}