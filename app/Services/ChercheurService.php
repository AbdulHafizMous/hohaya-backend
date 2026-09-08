<?php

namespace App\Services;

use App\Models\AccessContact;
use App\Models\Favori;
use App\Models\Property;
use App\Models\User;
use App\Models\Visite;
use App\Notifications\VisiteProposeeNotification;
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
        return Favori::with(['property.medias', 'property.mediaPrincipal'])
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
            'proposee_par'   => 'chercheur',
            'date_souhaitee' => $data['date_souhaitee'],
            'message'        => $data['message'] ?? null,
            'status'         => 'en_attente',
        ]);
    }

    /**
     * Le propriétaire propose une date de visite à un chercheur ayant débloqué son contact.
     * Le chercheur reçoit une notification et doit confirmer/refuser.
     */
    public function proposerVisite(User $proprietaire, array $data): Visite
    {
        $property = Property::find($data['id_property']);

        if (!$property || $property->id_user !== $proprietaire->id) {
            throw ValidationException::withMessages([
                'property' => ['Annonce introuvable ou non autorisée.'],
            ]);
        }

        $chercheur = User::find($data['id_chercheur']);

        if (!$chercheur) {
            throw ValidationException::withMessages([
                'chercheur' => ['Chercheur introuvable.'],
            ]);
        }

        $aDebloque = AccessContact::where('id_user', $chercheur->id)
            ->where('id_property', $property->id)
            ->exists();

        if (!$aDebloque) {
            throw ValidationException::withMessages([
                'chercheur' => ['Ce chercheur n\'a pas débloqué le contact de cette annonce.'],
            ]);
        }

        $existing = Visite::where('id_user', $chercheur->id)
            ->where('id_property', $property->id)
            ->whereIn('status', ['en_attente', 'confirmée'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'visite' => ['Une visite est déjà en cours pour ce chercheur sur cette annonce.'],
            ]);
        }

        $visite = Visite::create([
            'id_user'        => $chercheur->id,
            'id_property'    => $property->id,
            'proposee_par'   => 'proprietaire',
            'date_souhaitee' => $data['date_souhaitee'],
            'message'        => $data['message'] ?? null,
            'status'         => 'en_attente',
        ]);

        if ($chercheur->email) {
            $chercheur->notify(new VisiteProposeeNotification($property, $visite));
        }

        return $visite;
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

    /**
     * Le chercheur confirme/refuse une visite proposée par le propriétaire.
     */
    public function repondreVisiteChercheur(User $user, int $visiteId, string $status): Visite
    {
        $visite = Visite::where('id', $visiteId)->where('id_user', $user->id)->first();

        if (!$visite) {
            throw ValidationException::withMessages([
                'visite' => ['Visite introuvable.'],
            ]);
        }

        if ($visite->proposee_par !== 'proprietaire') {
            throw ValidationException::withMessages([
                'visite' => ['Cette visite n\'a pas été proposée par le propriétaire.'],
            ]);
        }

        if (!in_array($status, ['confirmée', 'refusée'])) {
            throw ValidationException::withMessages([
                'status' => ['Statut invalide. Valeurs : confirmée, refusée.'],
            ]);
        }

        $visite->update(['status' => $status]);
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
            'proposee_par'      => $visite->proposee_par,
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
