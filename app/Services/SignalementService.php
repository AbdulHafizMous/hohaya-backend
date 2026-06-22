<?php

namespace App\Services;

use App\Enums\SignalementStatus;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SignalementService
{
    public function store(User $user, array $data): Signalement
    {
        return Signalement::create([
            'id_user'          => $user->id,
            'id_property'      => $data['id_property'] ?? null,
            'id_user_signale'  => $data['id_user_signale'] ?? null,
            'motif'            => $data['motif'],
            'description'      => $data['description'],
            'type_signalement' => $data['type_signalement'],
            'status'           => SignalementStatus::EN_ATTENTE->value,
            'created_by'       => $user->id,
        ]);
    }

    public function allSignalements(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Signalement::with(['user', 'property', 'userSignale']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type_signalement'])) {
            $query->where('type_signalement', $filters['type_signalement']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function treat(User $admin, int $id, string $status, ?string $note): Signalement
    {
        $signalement = Signalement::find($id);

        if (!$signalement) {
            throw ValidationException::withMessages([
                'signalement' => ['Signalement introuvable.'],
            ]);
        }

        if (!in_array($status, SignalementStatus::values())) {
            throw ValidationException::withMessages([
                'status' => ['Statut invalide.'],
            ]);
        }

        $signalement->update([
            'status'     => $status,
            'note_admin' => $note,
            'traite_par' => $admin->id,
            'traite_le'  => now(),
            'updated_by' => $admin->id,
        ]);

        return $signalement->fresh(['user', 'property', 'adminTraitant']);
    }

    public function formatSignalement(Signalement $signalement): array
    {
        return [
            'id'               => $signalement->id,
            'motif'            => $signalement->motif,
            'description'      => $signalement->description,
            'type_signalement' => $signalement->type_signalement,
            'status'           => $signalement->status,
            'note_admin'       => $signalement->note_admin,
            'traite_le'        => $signalement->traite_le,
            'property'         => $signalement->property
                ? ['id' => $signalement->property->id, 'title' => $signalement->property->title]
                : null,
            'utilisateur'      => $signalement->user
                ? ['id' => $signalement->user->id, 'name' => $signalement->user->name]
                : null,
            'user_signale'     => $signalement->userSignale
                ? ['id' => $signalement->userSignale->id, 'name' => $signalement->userSignale->name]
                : null,
            'created_at'       => $signalement->created_at,
        ];
    }
}