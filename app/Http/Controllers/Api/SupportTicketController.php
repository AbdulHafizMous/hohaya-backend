<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketCategorie;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Support', description: 'Tickets de support client')]
class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketService $supportService) {}

    #[OA\Get(
        path: '/api/support/tickets',
        summary: 'Mes tickets de support',
        security: [['sanctum' => []]],
        tags: ['Support'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des tickets')]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $tickets = $this->supportService->myTickets($request->user(), $perPage);

        return $this->sendApiResponse(
            $tickets->through(fn($t) => $this->supportService->formatTicket($t)),
            'Tickets récupérés.'
        );
    }

    #[OA\Post(
        path: '/api/support/tickets',
        summary: 'Créer un ticket de support',
        security: [['sanctum' => []]],
        tags: ['Support'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['sujet', 'message', 'categorie'],
            properties: [
                new OA\Property(property: 'sujet', type: 'string', example: 'Problème avec mon annonce'),
                new OA\Property(property: 'message', type: 'string', example: 'Mon annonce n\'apparaît pas dans les résultats...'),
                new OA\Property(property: 'categorie', type: 'string', enum: TicketCategorie::class),
                new OA\Property(property: 'canal_preference', type: 'string', enum: ['email', 'whatsapp', 'appel']),
                new OA\Property(property: 'telephone_contact', type: 'string', example: '+22997000000'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Ticket créé'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = $this->supportService->store($request->user(), $request->validated());

        return $this->sendApiResponse(
            $this->supportService->formatTicket($ticket),
            'Ticket créé. Notre équipe vous répondra dans les plus brefs délais.',
            true,
            201
        );
    }

    #[OA\Post(
        path: '/api/support/tickets/{id}/respond',
        summary: 'Répondre à un ticket (admin)',
        security: [['sanctum' => []]],
        tags: ['Support'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reponse'],
            properties: [
                new OA\Property(property: 'reponse', type: 'string', example: 'Bonjour, votre annonce a bien été publiée...'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Réponse envoyée'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Ticket introuvable'),
        ]
    )]
    public function respond(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $request->validate([
            'reponse' => 'required|string|max:5000',
        ]);

        try {
            $ticket = $this->supportService->respond($request->user(), $id, $request->reponse);
            return $this->sendApiResponse(
                $this->supportService->formatTicket($ticket),
                'Réponse envoyée au client.'
            );
        } catch (ValidationException $e) {
            return $this->sendApiResponse(null, 'Ticket introuvable.', false, 404);
        }
    }

    #[OA\Patch(
        path: '/api/support/tickets/{id}/close',
        summary: 'Fermer un ticket (admin)',
        security: [['sanctum' => []]],
        tags: ['Support'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Ticket fermé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Ticket introuvable'),
        ]
    )]
    public function close(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        try {
            $ticket = $this->supportService->close($request->user(), $id);
            return $this->sendApiResponse(
                $this->supportService->formatTicket($ticket),
                'Ticket fermé avec succès.'
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return $this->sendApiResponse(['errors' => $errors], current($errors)[0], false, 422);
        }
    }

    #[OA\Get(
        path: '/api/admin/support/tickets',
        summary: 'Tous les tickets (admin)',
        security: [['sanctum' => []]],
        tags: ['Support'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: TicketStatus::class)),
            new OA\Parameter(name: 'categorie', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: TicketCategorie::class)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tous les tickets'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->sendApiResponse(null, 'Action réservée aux administrateurs.', false, 403);
        }

        $filters = $request->only(['status', 'categorie']);
        $perPage = (int) $request->input('per_page', 15);
        $tickets = $this->supportService->allTickets($filters, $perPage);

        return $this->sendApiResponse(
            $tickets->through(fn($t) => $this->supportService->formatTicket($t)),
            'Tickets récupérés.'
        );
    }
}
