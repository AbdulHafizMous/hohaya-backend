<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PropertyMediaService
{
    // Extensions autorisées par type
    const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];
    const VIDEO_MIMES = ['mp4', 'mov', 'avi', 'mkv', 'webm'];

    /**
     * Uploader et attacher des médias à une propriété
     */
    public function upload(User $user, Property $property, array $mediasData): Property
    {
        $this->checkOwnership($user, $property);

        $currentCount = $property->medias()->count();
        $newCount     = count($mediasData);

        if ($currentCount + $newCount > 20) {
            throw ValidationException::withMessages([
                'medias' => ['Maximum 20 médias par annonce. Vous en avez déjà ' . $currentCount . '.'],
            ]);
        }

        $hasPrincipale = $property->medias()->where('is_principale', true)->exists();

        foreach ($mediasData as $index => $mediaData) {
            /** @var UploadedFile $fichier */
            $fichier = $mediaData['fichier'];
            $type    = $mediaData['type'];
            $zone    = $mediaData['zone'];

            // Validation du mime selon le type déclaré
            $this->validateMime($fichier, $type);

            $folder = $type === MediaType::IMAGE->value ? 'images' : 'videos';
            $path   = $fichier->store("properties/{$property->id}/{$folder}", 'public');
            $url    = Storage::disk('public')->url($path);

            // La première image uploadée devient principale si aucune n'existe
            $isPrincipale = false;
            if (!$hasPrincipale && $type === MediaType::IMAGE->value && $index === 0) {
                $isPrincipale  = true;
                $hasPrincipale = true;
            }

            // Si le user l'a explicitement marqué comme principale
            if (!empty($mediaData['is_principale']) && $type === MediaType::IMAGE->value) {
                // Retirer l'ancienne principale
                $property->medias()->where('is_principale', true)->update(['is_principale' => false]);
                $isPrincipale = true;
            }

            PropertyMedia::create([
                'id_property'    => $property->id,
                'type'           => $type,
                'zone'           => $zone,
                'url'            => $url,
                'chemin'         => $path,
                'nom_original'   => $fichier->getClientOriginalName(),
                'taille'         => $fichier->getSize(),
                'mime_type'      => $fichier->getMimeType(),
                'is_principale'  => $isPrincipale,
                'ordre'          => $currentCount + $index,
                'description'    => $mediaData['description'] ?? null,
                'created_by'     => $user->id,
            ]);
        }

        return $property->fresh(['medias', 'mediaPrincipal', 'proprietaire']);
    }

    /**
     * Supprimer un média
     */
    public function delete(User $user, Property $property, int $mediaId): Property
    {
        $this->checkOwnership($user, $property);

        $media = PropertyMedia::where('id', $mediaId)
            ->where('id_property', $property->id)
            ->first();

        if (!$media) {
            throw ValidationException::withMessages([
                'media' => ['Média introuvable.'],
            ]);
        }

        // Garder au moins une image
        if ($media->type === MediaType::IMAGE->value) {
            $imageCount = $property->images()->count();
            if ($imageCount <= 1) {
                throw ValidationException::withMessages([
                    'media' => ['Vous devez conserver au moins une photo sur votre annonce.'],
                ]);
            }
        }

        Storage::disk('public')->delete($media->chemin);
        $media->delete();

        // Si c'était la principale, promouvoir la suivante
        if ($media->is_principale) {
            $nextImage = $property->images()->first();
            if ($nextImage) {
                $nextImage->update(['is_principale' => true]);
            }
        }

        return $property->fresh(['medias', 'mediaPrincipal']);
    }

    /**
     * Définir la photo principale
     */
    public function setPrincipal(User $user, Property $property, int $mediaId): Property
    {
        $this->checkOwnership($user, $property);

        $media = PropertyMedia::where('id', $mediaId)
            ->where('id_property', $property->id)
            ->where('type', MediaType::IMAGE->value)
            ->first();

        if (!$media) {
            throw ValidationException::withMessages([
                'media' => ['Image introuvable. Seules les images peuvent être définies comme principale.'],
            ]);
        }

        $property->medias()->update(['is_principale' => false]);
        $media->update(['is_principale' => true]);

        return $property->fresh(['medias', 'mediaPrincipal']);
    }

    /**
     * Réordonner les médias
     */
    public function reorder(User $user, Property $property, array $orderedIds): Property
    {
        $this->checkOwnership($user, $property);

        foreach ($orderedIds as $ordre => $mediaId) {
            PropertyMedia::where('id', $mediaId)
                ->where('id_property', $property->id)
                ->update(['ordre' => $ordre]);
        }

        return $property->fresh(['medias', 'mediaPrincipal']);
    }

    /**
     * Formater un média pour la réponse API
     */
    public function formatMedia(PropertyMedia $media): array
    {
        return [
            'id'            => $media->id,
            'type'          => $media->type,
            'zone'          => $media->zone,
            'zone_label'    => \App\Enums\PropertyZone::labels()[$media->zone] ?? $media->zone,
            'url'           => $media->url,
            'nom_original'  => $media->nom_original,
            'taille'        => $media->taille,
            'mime_type'     => $media->mime_type,
            'duree_secondes'=> $media->duree_secondes,
            'is_principale' => $media->is_principale,
            'ordre'         => $media->ordre,
            'description'   => $media->description,
        ];
    }

    // ── Privé ────────────────────────────────────────────────────────

    private function checkOwnership(User $user, Property $property): void
    {
        if ($property->id_user !== $user->id && !$user->isAdmin()) {
            throw ValidationException::withMessages([
                'property' => ['Vous n\'êtes pas autorisé à modifier cette annonce.'],
            ]);
        }
    }

    private function validateMime(UploadedFile $fichier, string $type): void
    {
        $extension = strtolower($fichier->getClientOriginalExtension());

        $allowed = $type === MediaType::IMAGE->value
            ? self::IMAGE_MIMES
            : self::VIDEO_MIMES;

        if (!in_array($extension, $allowed)) {
            $acceptes = implode(', ', $allowed);
            throw ValidationException::withMessages([
                'medias' => ["Extension non autorisée pour le type {$type}. Acceptés : {$acceptes}"],
            ]);
        }
    }
}