<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PropertyService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Property::with(['medias', 'mediaPrincipal', 'proprietaire'])
            ->where('is_verified', true)
            ->where('status', PropertyStatus::DISPONIBLE->value);

        if (!empty($filters['ville'])) {
            $query->where('ville', 'like', '%' . $filters['ville'] . '%');
        }
        if (!empty($filters['quartier'])) {
            $query->where('quartier', 'like', '%' . $filters['quartier'] . '%');
        }
        if (!empty($filters['commune'])) {
            $query->where('commune', 'like', '%' . $filters['commune'] . '%');
        }
        if (!empty($filters['type_logement'])) {
            $query->where('type_logement', $filters['type_logement']);
        }
        if (!empty($filters['prix_min'])) {
            $query->where('prix_loyer', '>=', $filters['prix_min']);
        }
        if (!empty($filters['prix_max'])) {
            $query->where('prix_loyer', '<=', $filters['prix_max']);
        }
        if (!empty($filters['nb_pieces'])) {
            $query->where('nb_pieces', $filters['nb_pieces']);
        }
        if (isset($filters['meuble'])) {
            $query->where('meuble', (bool) $filters['meuble']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function myProperties(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Property::with(['medias', 'mediaPrincipal'])
            ->where('id_user', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function show(int $id): Property
    {
        $property = Property::with(['medias', 'mediaPrincipal', 'proprietaire'])->find($id);

        if (!$property) {
            throw ValidationException::withMessages([
                'property' => ['Annonce introuvable.'],
            ]);
        }

        return $property;
    }

    public function store(User $user, array $data): Property
    {
        if (!$user->is_verified) {
            throw ValidationException::withMessages([
                'owner' => ['Votre compte doit être vérifié avant de publier une annonce.'],
            ]);
        }

        $property = Property::create([
            'id_user'             => $user->id,
            'title'               => $data['title'],
            'description'         => $data['description'],
            'indications_acces'   => $data['indications_acces'] ?? null,
            'quartier'            => $data['quartier'],
            'commune'             => $data['commune'] ?? null,
            'ville'               => $data['ville'],
            'pays'                => $data['pays'] ?? 'Bénin',
            'prix_loyer'          => $data['prix_loyer'],
            'devise'              => $data['devise'] ?? 'XOF',
            'type_logement'       => $data['type_logement'],
            'condition'           => $data['condition'],
            'nb_avance'           => $data['nb_avance'] ?? 3,
            'caution_electricite' => $data['caution_electricite'] ?? null,
            'caution_eau'         => $data['caution_eau'] ?? null,
            'nb_pieces'           => $data['nb_pieces'],
            'date_debut_louer'    => $data['date_debut_louer'] ?? null,
            'eau_courante'        => $data['eau_courante'] ?? true,
            'electricite'         => $data['electricite'] ?? true,
            'gardien'             => $data['gardien'] ?? false,
            'parking'             => $data['parking'] ?? false,
            'meuble'              => $data['meuble'] ?? false,
            'status'              => PropertyStatus::DISPONIBLE->value,
            'is_verified'         => false,
            'created_by'          => $user->id,
        ]);

        return $property->load(['mediaPrincipal', 'proprietaire']);
    }

    public function update(User $user, Property $property, array $data): Property
    {
        $this->checkOwnership($user, $property);

        $data['updated_by'] = $user->id;

        $triggerFields = ['title', 'description', 'quartier', 'ville', 'prix_loyer', 'type_logement'];
        $needsReview   = collect($triggerFields)->some(fn($f) => isset($data[$f]));

        if ($needsReview && $property->is_verified) {
            $data['is_verified'] = false;
        }

        $property->update($data);

        return $property->fresh(['medias', 'mediaPrincipal', 'proprietaire']);
    }

    public function destroy(User $user, Property $property): void
    {
        $this->checkOwnership($user, $property);

        // Supprimer tous les fichiers médias du storage
        foreach ($property->medias as $media) {
            Storage::disk('public')->delete($media->chemin);
        }

        $property->update(['deleted_by' => $user->id]);
        $property->delete();
    }

    public function changeStatus(User $user, Property $property, string $status): Property
    {
        $this->checkOwnership($user, $property);

        if (!in_array($status, PropertyStatus::values())) {
            throw ValidationException::withMessages([
                'status' => ['Statut invalide. Valeurs : ' . implode(', ', PropertyStatus::values())],
            ]);
        }

        $property->update(['status' => $status, 'updated_by' => $user->id]);

        return $property->fresh();
    }

    public function verify(Property $property, bool $verified): Property
    {
        $status = $verified
            ? PropertyStatus::DISPONIBLE->value
            : PropertyStatus::SUSPENDU->value;

        $property->update([
            'is_verified' => $verified,
            'status'      => $status,
        ]);

        return $property->fresh(['medias', 'mediaPrincipal', 'proprietaire']);
    }

    public function checkOwnership(User $user, Property $property): void
    {
        if ($property->id_user !== $user->id && !$user->isAdmin()) {
            throw ValidationException::withMessages([
                'property' => ['Vous n\'êtes pas autorisé à modifier cette annonce.'],
            ]);
        }
    }

    public function formatProperty(Property $property): array
    {
        $medias     = $property->relationLoaded('medias') ? $property->medias : collect();
        $images     = $medias->where('type', 'image')->values();
        $videos     = $medias->where('type', 'video')->values();
        $principale = $property->relationLoaded('mediaPrincipal') ? $property->mediaPrincipal : null;

        return [
            'id'                  => $property->id,
            'title'               => $property->title,
            'description'         => $property->description,
            'indications_acces'   => $property->indications_acces,
            'quartier'            => $property->quartier,
            'commune'             => $property->commune,
            'ville'               => $property->ville,
            'pays'                => $property->pays,
            'prix_loyer'          => $property->prix_loyer,
            'devise'              => $property->devise,
            'type_logement'       => $property->type_logement,
            'condition'           => $property->condition,
            'nb_avance'           => $property->nb_avance,
            'caution_electricite' => $property->caution_electricite,
            'caution_eau'         => $property->caution_eau,
            'nb_pieces'           => $property->nb_pieces,
            'eau_courante'        => $property->eau_courante,
            'electricite'         => $property->electricite,
            'gardien'             => $property->gardien,
            'parking'             => $property->parking,
            'meuble'              => $property->meuble,
            'status'              => $property->status,
            'is_verified'         => $property->is_verified,
            'date_debut_louer'    => $property->date_debut_louer,
            'created_at'          => $property->created_at,
            'proprietaire'        => $property->relationLoaded('proprietaire') && $property->proprietaire
                ? [
                    'id'    => $property->proprietaire->id,
                    'name'  => $property->proprietaire->name,
                    'phone' => $property->proprietaire->phone,
                ]
                : null,
            'media_principal'     => $principale
                ? [
                    'id'          => $principale->id,
                    'url'         => $principale->url,
                    'zone'        => $principale->zone,
                    'zone_label'  => \App\Enums\PropertyZone::labels()[$principale->zone] ?? $principale->zone,
                ]
                : null,
            'images'              => $images->map(fn($m) => [
                'id'            => $m->id,
                'url'           => $m->url,
                'zone'          => $m->zone,
                'zone_label'    => \App\Enums\PropertyZone::labels()[$m->zone] ?? $m->zone,
                'is_principale' => $m->is_principale,
                'ordre'         => $m->ordre,
                'description'   => $m->description,
            ])->toArray(),
            'videos'              => $videos->map(fn($m) => [
                'id'             => $m->id,
                'url'            => $m->url,
                'zone'           => $m->zone,
                'zone_label'     => \App\Enums\PropertyZone::labels()[$m->zone] ?? $m->zone,
                'duree_secondes' => $m->duree_secondes,
                'ordre'          => $m->ordre,
                'description'    => $m->description,
            ])->toArray(),
            'total_medias'        => $medias->count(),
        ];
    }
}