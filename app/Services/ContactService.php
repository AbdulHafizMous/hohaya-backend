<?php

namespace App\Services;

use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use App\Models\AccessContact;
use App\Models\Paiement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(private KKiaPayService $kkiapay) {}

    /**
     * Vérifier si un user a accès au contact d'une propriété
     */
    public function hasAccess(User $user, Property $property): bool
    {
        // Le propriétaire a toujours accès
        if ($property->id_user === $user->id) return true;

        return AccessContact::where('id_user', $user->id)
            ->where('id_property', $property->id)
            ->exists();
    }

    /**
     * Initier le déblocage d'un contact
     */
    public function initierDeblocage(User $user, int $propertyId): array
    {
        $property = Property::find($propertyId);

        if (!$property) {
            throw ValidationException::withMessages([
                'property' => ['Annonce introuvable.'],
            ]);
        }

        if (!$property->is_verified) {
            throw ValidationException::withMessages([
                'property' => ['Cette annonce n\'est pas encore disponible.'],
            ]);
        }

        // Vérifier s'il n'a pas déjà accès
        if ($this->hasAccess($user, $property)) {
            return $this->getContactInfo($user, $property);
        }

        $prix = (float) config('kkiapay.deblocage_prix', 500);

        // Créer paiement en attente
        $paiement = Paiement::create([
            'id_user'    => $user->id,
            'type'       => PaiementType::DEBLOCAGE->value,
            'status'     => PaiementStatus::EN_ATTENTE->value,
            'montant'    => $prix,
            'devise'     => 'XOF',
            'id_property' => $property->id,
            'created_by' => $user->id,
        ]);

        return [
            'paiement_id'        => $paiement->id,
            'montant'            => $prix,
            'devise'             => 'XOF',
            'property_id'        => $property->id,
            'property_title'     => $property->title,
            'kkiapay_public_key' => config('kkiapay.public_key'),
            'sandbox'            => config('kkiapay.sandbox'),
            'instructions'       => 'Payez ' . $prix . ' XOF via KKiaPay pour accéder au contact du propriétaire. Appelez ensuite POST /api/contacts/confirmer.',
        ];
    }

    /**
     * Confirmer le déblocage après paiement
     */
    public function confirmerDeblocage(User $user, int $paiementId, string $transactionId): array
    {
        $paiement = Paiement::where('id', $paiementId)
            ->where('id_user', $user->id)
            ->where('type', PaiementType::DEBLOCAGE->value)
            ->where('status', PaiementStatus::EN_ATTENTE->value)
            ->with('property.proprietaire')
            ->first();

        if (!$paiement) {
            throw ValidationException::withMessages([
                'paiement' => ['Paiement introuvable ou déjà traité.'],
            ]);
        }

        // Vérifier KKiaPay
        $transactionData = $this->kkiapay->verifyTransaction($transactionId);

        if (!$this->kkiapay->isSuccessful($transactionData)) {
            $paiement->update([
                'status'                 => PaiementStatus::ECHOUE->value,
                'kkiapay_transaction_id' => $transactionId,
                'kkiapay_response'       => $transactionData,
                'raison_echec'           => $transactionData['message'] ?? 'Paiement non abouti',
            ]);

            throw ValidationException::withMessages([
                'paiement' => ['Le paiement n\'a pas abouti. Veuillez réessayer.'],
            ]);
        }

        return DB::transaction(function () use ($user, $paiement, $transactionId, $transactionData) {
            $paiement->update([
                'status'                 => PaiementStatus::SUCCES->value,
                'kkiapay_transaction_id' => $transactionId,
                'kkiapay_response'       => $transactionData,
                'telephone_paiement'     => $this->kkiapay->getPhone($transactionData),
                'paye_le'                => now(),
            ]);

            AccessContact::create([
                'id_user'     => $user->id,
                'id_property' => $paiement->id_property,
                'id_paiement' => $paiement->id,
            ]);

            Log::info('Contact débloqué', [
                'user_id'     => $user->id,
                'property_id' => $paiement->id_property,
            ]);

            return $this->getContactInfo($user, $paiement->property);
        });
    }

    /**
     * Historique des contacts débloqués par un chercheur
     */
    public function historiqueDeblocages(User $user, int $perPage = 15)
    {
        return AccessContact::with(['property.mediaPrincipal', 'paiement'])
            ->where('id_user', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Retourner les informations de contact du propriétaire
     */
    private function getContactInfo(User $user, Property $property): array
    {
        $proprietaire = $property->proprietaire;

        return [
            'access'        => true,
            'property_id'   => $property->id,
            'property_title' => $property->title,
            'proprietaire'  => [
                'nom'       => $proprietaire->name,
                'telephone' => $proprietaire->phone,
                'email'     => $proprietaire->email,
            ],
            'message'       => 'Contact débloqué. Vous pouvez maintenant contacter directement le propriétaire.',
        ];
    }
}