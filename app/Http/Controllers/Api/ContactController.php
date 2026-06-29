<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Contacts', description: 'Déblocage des contacts propriétaires')]
class ContactController extends Controller
{
    public function __construct(private ContactService $contactService) {}

    #[OA\Post(
        path: '/api/contacts/initier',
        summary: 'Initier le déblocage d\'un contact propriétaire (étape 1)',
        description: 'Si l\'utilisateur a déjà payé, retourne directement le contact. Sinon, retourne les infos de paiement KKiaPay.',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['property_id'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'integer', example: 3,
                        description: 'ID de l\'annonce dont on veut débloquer le contact'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200,
                description: 'Contact retourné directement (déjà payé) ou informations de paiement KKiaPay'),
            new OA\Response(response: 422, description: 'Annonce invalide'),
        ]
    )]
    public function initier(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
        ]);

        try {
            $result = $this->contactService->initierDeblocage($request->user(), $request->property_id);
            return $this->sendApiResponse($result,
                isset($result['access']) ? 'Contact disponible.' : 'Procédez au paiement pour débloquer ce contact.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Post(
        path: '/api/contacts/confirmer',
        summary: 'Confirmer le paiement et obtenir le contact (étape 2)',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['paiement_id', 'transaction_id'],
                properties: [
                    new OA\Property(property: 'paiement_id', type: 'integer', example: 12,
                        description: 'ID du paiement retourné par /api/contacts/initier'),
                    new OA\Property(property: 'transaction_id', type: 'string',
                        example: 'kkp_txn_xyz789',
                        description: 'Transaction ID KKiaPay après paiement réussi'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contact du propriétaire débloqué'),
            new OA\Response(response: 422, description: 'Paiement invalide'),
        ]
    )]
    public function confirmer(Request $request): JsonResponse
    {
        $request->validate([
            'paiement_id'    => 'required|integer',
            'transaction_id' => 'required|string',
        ]);

        try {
            $result = $this->contactService->confirmerDeblocage(
                $request->user(),
                $request->paiement_id,
                $request->transaction_id
            );
            return $this->sendApiResponse($result, 'Contact débloqué avec succès.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Get(
        path: '/api/contacts/historique',
        summary: 'Historique des contacts débloqués',
        security: [['sanctum' => []]],
        tags: ['Contacts'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false,
                schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Historique des contacts débloqués')]
    )]
    public function historique(Request $request): JsonResponse
    {
        $perPage    = (int) $request->input('per_page', 15);
        $historique = $this->contactService->historiqueDeblocages($request->user(), $perPage);

        return $this->sendApiResponse(
            $historique->through(fn($ac) => [
                'id'             => $ac->id,
                'property'       => [
                    'id'    => $ac->property->id,
                    'title' => $ac->property->title,
                    'ville' => $ac->property->ville,
                    'media_principal' => $ac->property->mediaPrincipal
                        ? ['url' => $ac->property->mediaPrincipal->url]
                        : null,
                ],
                'paiement'       => [
                    'montant'  => $ac->paiement->montant,
                    'devise'   => $ac->paiement->devise,
                    'paye_le'  => $ac->paiement->paye_le?->format('d/m/Y'),
                ],
                'debloque_le'    => $ac->created_at->format('d/m/Y'),
            ]),
            'Historique récupéré.'
        );
    }
}