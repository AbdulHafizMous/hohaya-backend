<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Réservations', description: 'Réservation d\'un logement via paiement de l\'avance')]
class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservationService) {}

    #[OA\Post(
        path: '/api/reservations/initier',
        summary: 'Initier la réservation d\'un logement (étape 1)',
        security: [['sanctum' => []]],
        tags: ['Réservations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['property_id'],
            properties: [new OA\Property(property: 'property_id', type: 'integer', example: 3)]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Informations de paiement FedaPay'),
            new OA\Response(response: 422, description: 'Annonce invalide ou indisponible'),
        ]
    )]
    public function initier(Request $request): JsonResponse
    {
        $request->validate(['property_id' => 'required|integer|exists:properties,id']);

        try {
            $result = $this->reservationService->initierReservation($request->user(), $request->property_id);
            return $this->sendApiResponse($result, 'Procédez au paiement pour réserver ce logement.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Post(
        path: '/api/reservations/guest-initier',
        summary: 'Initier une réservation sans compte (crée/retrouve un compte à partir des infos de paiement)',
        tags: ['Réservations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['property_id', 'name'],
            properties: [
                new OA\Property(property: 'property_id', type: 'integer', example: 3),
                new OA\Property(property: 'name', type: 'string', example: 'Jean Dupont'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'phone', type: 'string', example: '+22997000000'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Compte connecté + informations de paiement'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function guestInitier(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'name'        => 'required|string|min:2',
            'email'       => 'required|email',
            'phone'       => 'required|string|min:6',
        ], [
            'property_id.required' => 'Annonce introuvable.',
            'name.required'        => 'Le nom est obligatoire.',
            'email.required'       => 'L\'email est obligatoire.',
            'phone.required'       => 'Le téléphone est obligatoire.',
        ]);

        try {
            $result = $this->reservationService->initierReservationInvite($request->only(['property_id', 'name', 'email', 'phone']));
            return $this->sendApiResponse($result, 'Procédez au paiement pour réserver ce logement.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Post(
        path: '/api/reservations/confirmer',
        summary: 'Confirmer le paiement de l\'avance et valider la réservation (étape 2)',
        security: [['sanctum' => []]],
        tags: ['Réservations'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['paiement_id', 'transaction_id'],
            properties: [
                new OA\Property(property: 'paiement_id', type: 'integer', example: 12),
                new OA\Property(property: 'transaction_id', type: 'string', example: 'kkp_txn_xyz789'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Réservation confirmée'),
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
            $result = $this->reservationService->confirmerReservation(
                $request->user(),
                $request->paiement_id,
                $request->transaction_id
            );
            $message = isset($result['pending']) ? $result['message'] : 'Réservation confirmée avec succès.';
            return $this->sendApiResponse($result, $message);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Get(
        path: '/api/owner/locataires',
        summary: 'Mes locataires (chercheurs ayant réservé mes logements)',
        security: [['sanctum' => []]],
        tags: ['Réservations'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des locataires')]
    )]
    public function locatairesProprietaire(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $locataires = $this->reservationService->locatairesProprietaire($request->user(), $perPage);

        return $this->sendApiResponse(
            $locataires->through(fn($p) => $this->reservationService->formatLocataire($p)),
            'Locataires récupérés.'
        );
    }

    #[OA\Get(
        path: '/api/admin/locataires',
        summary: 'Tous les locataires de la plateforme (admin)',
        security: [['sanctum' => []]],
        tags: ['Réservations'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des locataires'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function locatairesAdmin(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $perPage = (int) $request->input('per_page', 15);
        $locataires = $this->reservationService->locatairesAdmin($perPage);

        return $this->sendApiResponse(
            $locataires->through(fn($p) => $this->reservationService->formatLocataire($p, true)),
            'Locataires récupérés.'
        );
    }
}
