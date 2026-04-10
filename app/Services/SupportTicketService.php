<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function myTickets(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return SupportTicket::where('id_user', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function allTickets(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SupportTicket::with('user');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['categorie'])) {
            $query->where('categorie', $filters['categorie']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function store(User $user, array $data): SupportTicket
    {
        return SupportTicket::create([
            'id_user'           => $user->id,
            'sujet'             => $data['sujet'],
            'message'           => $data['message'],
            'categorie'         => $data['categorie'],
            'status'            => TicketStatus::OUVERT->value,
            'canal_preference'  => $data['canal_preference'] ?? 'email',
            'telephone_contact' => $data['telephone_contact'] ?? null,
            'created_by'        => $user->id,
        ]);
    }

    public function respond(User $admin, int $ticketId, string $reponse): SupportTicket
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            throw ValidationException::withMessages([
                'ticket' => ['Ticket introuvable.'],
            ]);
        }

        $ticket->update([
            'reponse_admin' => $reponse,
            'repondu_par'   => $admin->id,
            'repondu_le'    => now(),
            'status'        => TicketStatus::EN_COURS->value,
            'updated_by'    => $admin->id,
        ]);

        return $ticket->fresh(['user', 'adminRepondant']);
    }

    public function close(User $admin, int $ticketId): SupportTicket
    {
        $ticket = SupportTicket::find($ticketId);

        if (!$ticket) {
            throw ValidationException::withMessages([
                'ticket' => ['Ticket introuvable.'],
            ]);
        }

        if ($ticket->status === TicketStatus::FERME->value) {
            throw ValidationException::withMessages([
                'ticket' => ['Ce ticket est déjà fermé.'],
            ]);
        }

        $ticket->update([
            'status'     => TicketStatus::FERME->value,
            'updated_by' => $admin->id,
        ]);

        return $ticket->fresh();
    }

    public function formatTicket(SupportTicket $ticket): array
    {
        return [
            'id'                 => $ticket->id,
            'sujet'              => $ticket->sujet,
            'message'            => $ticket->message,
            'categorie'          => $ticket->categorie,
            'status'             => $ticket->status,
            'canal_preference'   => $ticket->canal_preference,
            'telephone_contact'  => $ticket->telephone_contact,
            'reponse_admin'      => $ticket->reponse_admin,
            'repondu_le'         => $ticket->repondu_le,
            'repondu_par'        => $ticket->adminRepondant
                ? ['id' => $ticket->adminRepondant->id, 'name' => $ticket->adminRepondant->name]
                : null,
            'utilisateur'        => $ticket->user
                ? ['id' => $ticket->user->id, 'name' => $ticket->user->name, 'email' => $ticket->user->email]
                : null,
            'created_at'         => $ticket->created_at,
            'updated_at'         => $ticket->updated_at,
        ];
    }
}