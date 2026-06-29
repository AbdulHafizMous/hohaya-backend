<?php

namespace App\Services;

use App\Models\Favori;
use App\Models\Property;
use App\Models\User;
use App\Models\Visite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ChercheurService
{
    // ─── Favoris ──────────────────────────────────────────────────────

    public function toggleFavori(User $user, int $propertyId): array
    {
        $property = Property::find($propertyId);

        if (!$property || !$property->is_verified) {
            throw ValidationException::withMessages([
                'property' => ['Annonce introuvable.'],
            ]);
        }

        $existing = Favori::where('id_user', $user->id)
            ->where('id_property', $propertyId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['favori' => false, 'message' => 'Retiré des favoris.'];
        }

        Favori::create(['id_user' => $user->id, 'id_property' => $propertyId]);
        return ['favori' => true, 'message' => 'Ajouté aux favoris.'];
    }

    public function mesFavoris(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Favori::with(['property.mediaPrincipal'])
            ->where('id_user', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    // ─── Visites ──────────────────────────────────────────────────────

    public function demanderVisite(User $user, array $data): Visite
    {
        $property = Property::find($data['id_property']);

        if (!$property || !$property->is_verified) {
            throw ValidationException::withMessages([
                'property' => ['Annonce introuvable ou non disponible.'],
            ]);
        }

        if ($property->id_user === $user->id) {
            throw ValidationException::withMessages([
                'property' => ['Vous ne pouvez pas demander une visite de votre propre annonce.'],
            ]);
        }

        $existing = Visite::where('id_user', $user->id)
            ->where('id_property', $data['id_property'])
            ->whereIn('status', ['en_attente', 'confirmée'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'visite' => ['Vous avez déjà une demande de visite en cours pour cette annonce.'],
            ]);
        }

        return Visite::create([
            'id_user'        => $user->id,
            'id_property'    => $data['id_property'],
            'date_souhaitee' => $data['date_souhaitee'],
            'message'        => $data['message'] ?? null,
            'status'         => 'en_attente',
        ]);
    }

    public function mesVisites(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Visite::with('property.mediaPrincipal')
            ->where('id_user', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function annulerVisite(User $user, int $visiteId): Visite
    {
        $visite = Visite::where('id', $visiteId)
            ->where('id_user', $user->id)
            ->first();

        if (!$visite) {
            throw ValidationException::withMessages([
                'visite' => ['Visite introuvable.'],
            ]);
        }

        if ($visite->status !== 'en_attente') {
            throw ValidationException::withMessages([
                'visite' => ['Seules les visites en attente peuvent être annulées.'],
            ]);
        }

        $visite->update(['status' => 'annulée']);
        return $visite->fresh();
    }

    public function repondreVisite(User $user, int $visiteId, string $status, ?string $note): Visite
    {
        $visite = Visite::with('property')->where('id', $visiteId)->first();

        if (!$visite) {
            throw ValidationException::withMessages([
                'visite' => ['Visite introuvable.'],
            ]);
        }

        if ($visite->property->id_user !== $user->id) {
            throw ValidationException::withMessages([
                'visite' => ['Non autorisé.'],
            ]);
        }

        if (!in_array($status, ['confirmée', 'refusée'])) {
            throw ValidationException::withMessages([
                'status' => ['Statut invalide. Valeurs : confirmée, refusée.'],
            ]);
        }

        $visite->update([
            'status'            => $status,
            'note_proprietaire' => $note,
        ]);

        return $visite->fresh();
    }

    public function visitesRecues(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Visite::with(['user', 'property'])
            ->whereHas('property', fn($q) => $q->where('id_user', $user->id))
            ->latest()
            ->paginate($perPage);
    }

    public function formatVisite(Visite $visite): array
    {
        return [
            'id'                => $visite->id,
            'status'            => $visite->status,
            'date_souhaitee'    => $visite->date_souhaitee?->format('d/m/Y H:i'),
            'message'           => $visite->message,
            'note_proprietaire' => $visite->note_proprietaire,
            'property'          => $visite->property ? [
                'id'    => $visite->property->id,
                'title' => $visite->property->title,
                'ville' => $visite->property->ville,
            ] : null,
            'chercheur'         => $visite->user ? [
                'id'    => $visite->user->id,
                'name'  => $visite->user->name,
                'phone' => $visite->user->phone,
            ] : null,
            'created_at'        => $visite->created_at->format('d/m/Y'),
        ];
    }
}
