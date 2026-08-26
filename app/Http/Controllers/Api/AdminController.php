<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin', description: 'Back-office administrateur')]
class AdminController extends Controller
{
    public function __construct(
        private AdminService    $adminService,
        private PropertyService $propertyService,
    ) {}

    private function checkAdmin(Request $request): ?JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Réservé aux administrateurs.', false, 403);
        }
        return null;
    }

    #[OA\Get(
        path: '/api/admin/dashboard',
        summary: 'Statistiques globales du dashboard admin',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques globales'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;
        return $this->sendApiResponse($this->adminService->dashboard(), 'Dashboard récupéré.');
    }

    #[OA\Get(
        path: '/api/admin/revenus/mensuel',
        summary: 'Revenus mensuels sur les 12 derniers mois',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [new OA\Response(response: 200, description: 'Revenus par mois')]
    )]
    public function revenusMensuels(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;
        return $this->sendApiResponse($this->adminService->revenusParMois(), 'Revenus récupérés.');
    }

    #[OA\Get(
        path: '/api/admin/users',
        summary: 'Liste de tous les utilisateurs',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false,
                description: 'Filtrer par rôle',
                schema: new OA\Schema(type: 'string', enum: ['owner', 'seeker', 'admin'])),
            new OA\Parameter(name: 'search', in: 'query', required: false,
                description: 'Recherche par nom, email ou téléphone',
                schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_verified', in: 'query', required: false,
                description: 'Filtrer par statut de vérification',
                schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'deleted', in: 'query', required: false,
                description: 'Afficher uniquement les comptes supprimés',
                schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des utilisateurs')]
    )]
    public function users(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $filters = $request->only(['role', 'search', 'is_verified', 'deleted']);
        $users   = $this->adminService->listeUtilisateurs($filters, (int) $request->input('per_page', 20));

        return $this->sendApiResponse(
            $users->through(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'phone'        => $u->phone,
                'roles'        => $u->getRoleNames(),
                'is_verified'  => $u->is_verified,
                'is_suscribed' => $u->is_suscribed,
                'deleted_at'   => $u->deleted_at?->format('d/m/Y'),
                'created_at'   => $u->created_at->format('d/m/Y'),
            ]),
            'Utilisateurs récupérés.'
        );
    }

    #[OA\Patch(
        path: '/api/admin/users/{id}/verify',
        summary: 'Vérifier ou retirer la vérification d\'un propriétaire',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                description: 'ID de l\'utilisateur',
                schema: new OA\Schema(type: 'integer', example: 5)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['is_verified'],
                properties: [
                    new OA\Property(property: 'is_verified', type: 'boolean', example: true,
                        description: 'true = vérifier, false = retirer la vérification'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Vérification mise à jour'),
            new OA\Response(response: 422, description: 'Utilisateur non propriétaire'),
        ]
    )]
    public function verifierProprietaire(Request $request, int $id): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $request->validate(['is_verified' => 'required|boolean']);

        try {
            $user = $this->adminService->verifierProprietaire($id, $request->boolean('is_verified'));
            $msg  = $request->boolean('is_verified') ? 'Propriétaire vérifié.' : 'Vérification retirée.';
            return $this->sendApiResponse(['user_id' => $user->id, 'is_verified' => $user->is_verified], $msg);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Patch(
        path: '/api/admin/users/{id}/suspension',
        summary: 'Suspendre ou réactiver un compte utilisateur',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer', example: 5)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['suspendre'],
                properties: [
                    new OA\Property(property: 'suspendre', type: 'boolean', example: true,
                        description: 'true = suspendre le compte, false = réactiver'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte suspendu ou réactivé'),
            new OA\Response(response: 422, description: 'Utilisateur introuvable'),
        ]
    )]
    public function toggleSuspension(Request $request, int $id): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $request->validate(['suspendre' => 'required|boolean']);

        try {
            $user = $this->adminService->toggleSuspension($id, $request->boolean('suspendre'));
            $msg  = $request->boolean('suspendre') ? 'Compte suspendu.' : 'Compte réactivé.';
            return $this->sendApiResponse(['user_id' => $user->id], $msg);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Get(
        path: '/api/admin/properties/pending',
        summary: 'Annonces en attente de modération',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Annonces à modérer')]
    )]
    public function annoncesEnAttente(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $perPage    = (int) $request->input('per_page', 20);
        $properties = $this->adminService->annoncesEnAttente($perPage);

        return $this->sendApiResponse(
            $properties->through(fn($p) => $this->propertyService->formatProperty($p)),
            'Annonces en attente récupérées.'
        );
    }

    #[OA\Get(
        path: '/api/admin/export/paiements',
        summary: 'Exporter les paiements en CSV',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV des paiements'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function exportPaiements(Request $request): Response
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Non autorisé.');
        }

        $csv      = $this->adminService->exportPaiementsCSV();
        $filename = 'hohaya_paiements_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    #[OA\Get(
        path: '/api/admin/export/users',
        summary: 'Exporter les utilisateurs en CSV',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV des utilisateurs'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function exportUsers(Request $request): Response
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Non autorisé.');
        }

        $csv      = $this->adminService->exportUsersCSV();
        $filename = 'hohaya_users_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    #[OA\Get(
        path: '/api/admin/tarifs',
        summary: 'Liste complète des tarifs d\'abonnement (actifs et inactifs)',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Tarifs récupérés'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function tarifs(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $tarifs = \App\Models\TarifAbonnement::orderByRaw(
            "FIELD(type, 'mensuel', 'trimestriel', 'semestriel', 'annuel')"
        )->get();

        return $this->sendApiResponse($tarifs, 'Tarifs récupérés.');
    }

    #[OA\Patch(
        path: '/api/admin/tarifs/{type}',
        summary: 'Modifier un tarif d\'abonnement (montant, description, actif)',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true,
                description: 'Type d\'abonnement (mensuel, trimestriel, semestriel, annuel)',
                schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'montant', type: 'number', example: 5000),
            new OA\Property(property: 'devise', type: 'string', example: 'XOF'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'is_actif', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Tarif mis à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Type invalide'),
        ]
    )]
    public function updateTarif(Request $request, string $type): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        if (!in_array($type, \App\Enums\AbonnementType::values(), true)) {
            return $this->sendApiResponse(null, 'Type d\'abonnement invalide.', false, 422);
        }

        $exists = \App\Models\TarifAbonnement::where('type', $type)->exists();

        $request->validate([
            'montant'     => [$exists ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'devise'      => ['sometimes', 'string', 'max:10'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_actif'    => ['sometimes', 'boolean'],
        ]);

        $tarif = \App\Models\TarifAbonnement::updateOrCreate(
            ['type' => $type],
            array_merge(['devise' => 'XOF', 'is_actif' => true], $request->only(['montant', 'devise', 'description', 'is_actif']))
        );

        return $this->sendApiResponse($tarif, 'Tarif mis à jour.');
    }

    #[OA\Get(
        path: '/api/admin/settings',
        summary: 'Paramètres généraux modifiables (ex: prix de déblocage contact)',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Paramètres récupérés'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function settings(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        return $this->sendApiResponse([
            'deblocage_contact_prix' => (float) \App\Models\Setting::get(
                'deblocage_contact_prix',
                (string) config('fedapay.deblocage_prix', 500)
            ),
        ], 'Paramètres récupérés.');
    }

    #[OA\Patch(
        path: '/api/admin/settings/deblocage-prix',
        summary: 'Modifier le prix de déblocage d\'un contact propriétaire',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['montant'],
            properties: [new OA\Property(property: 'montant', type: 'number', example: 500)]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Prix mis à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Montant invalide'),
        ]
    )]
    public function updateDeblocagePrix(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $request->validate(['montant' => 'required|numeric|min:0']);

        \App\Models\Setting::set('deblocage_contact_prix', (string) $request->montant, $request->user()->id);

        return $this->sendApiResponse(
            ['deblocage_contact_prix' => (float) $request->montant],
            'Prix de déblocage mis à jour.'
        );
    }
}
