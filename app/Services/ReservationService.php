<?php

namespace App\Services;

use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use App\Enums\PropertyStatus;
use App\Models\Paiement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private FedaPayService $fedapay, private AuthService $authService, private ContactService $contactService) {}

    /**
     * Locataires (chercheurs ayant réservé et payé l'avance) pour les annonces d'un propriétaire.
     */
    public function locatairesProprietaire(User $owner, int $perPage = 15): LengthAwarePaginator
    {
        return Paiement::with(['user', 'property'])
            ->where('type', PaiementType::RESERVATION->value)
            ->where('status', PaiementStatus::SUCCES->value)
            ->whereHas('property', fn($q) => $q->where('id_user', $owner->id))
            ->latest('paye_le')
            ->paginate($perPage);
    }

    /**
     * Tous les locataires de la plateforme (vue admin), avec le propriétaire concerné.
     */
    public function locatairesAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return Paiement::with(['user', 'property.proprietaire'])
            ->where('type', PaiementType::RESERVATION->value)
            ->where('status', PaiementStatus::SUCCES->value)
            ->latest('paye_le')
            ->paginate($perPage);
    }

    public function formatLocataire(Paiement $paiement, bool $withProprietaire = false): array
    {
        return [
            'id'           => $paiement->id,
            'locataire'    => [
                'id'    => $paiement->user->id,
                'nom'   => $paiement->user->name,
                'email' => $paiement->user->email,
                'phone' => $paiement->user->phone,
            ],
            'property'     => $paiement->property ? [
                'id'    => $paiement->property->id,
                'title' => $paiement->property->title,
                'ville' => $paiement->property->ville,
            ] : null,
            'proprietaire' => $withProprietaire && $paiement->property?->proprietaire ? [
                'id'  => $paiement->property->proprietaire->id,
                'nom' => $paiement->property->proprietaire->name,
            ] : null,
            'montant'      => $paiement->montant,
            'devise'       => $paiement->devise,
            'paye_le'      => $paiement->paye_le?->format('d/m/Y'),
        ];
    }

    public function initierReservation(User $user, int $propertyId): array
    {
        $property = Property::find($propertyId);

        if (!$property || !$property->is_verified) {
            throw ValidationException::withMessages([
                'property' => ['Annonce introuvable.'],
            ]);
        }

        if ($property->status !== PropertyStatus::DISPONIBLE->value) {
            throw ValidationException::withMessages([
                'property' => ['Ce logement n\'est plus disponible à la réservation.'],
            ]);
        }

        $montant = (float) $property->prix_loyer * max(1, (int) $property->nb_avance);

        $paiement = Paiement::create([
            'id_user'     => $user->id,
            'type'        => PaiementType::RESERVATION->value,
            'status'      => PaiementStatus::EN_ATTENTE->value,
            'montant'     => $montant,
            'devise'      => 'XOF',
            'id_property' => $property->id,
            'created_by'  => $user->id,
        ]);

        return [
            'paiement_id'        => $paiement->id,
            'montant'            => $montant,
            'devise'             => 'XOF',
            'property_id'        => $property->id,
            'property_title'     => $property->title,
            'nb_avance'          => $property->nb_avance,
            'fedapay_public_key' => config('fedapay.public_key'),
            'sandbox'            => config('fedapay.sandbox'),
            'instructions'       => 'Payez ' . $montant . ' XOF via FedaPay pour réserver ce logement ('
                . $property->nb_avance . ' mois d\'avance). Appelez ensuite POST /api/reservations/confirmer.',
        ];
    }

    public function initierReservationInvite(array $data): array
    {
        $user = $this->contactService->findOrCreateGuest($data);
        $tokens = $this->authService->issueTokensFor($user);
        $result = $this->initierReservation($user, $data['property_id']);

        return array_merge($tokens, $result);
    }

    public function confirmerReservation(User $user, int $paiementId, string $transactionId): array
    {
        $paiement = Paiement::where('id', $paiementId)
            ->where('id_user', $user->id)
            ->where('type', PaiementType::RESERVATION->value)
            ->where('status', PaiementStatus::EN_ATTENTE->value)
            ->with('property')
            ->first();

        if (!$paiement) {
            throw ValidationException::withMessages([
                'paiement' => ['Paiement introuvable ou déjà traité.'],
            ]);
        }

        $transactionData = $this->fedapay->verifyTransaction($transactionId);

        if ($this->fedapay->isPending($transactionData)) {
            $paiement->update([
                'fedapay_transaction_id' => $transactionId,
                'fedapay_response'       => $transactionData,
            ]);

            return ['pending' => true, 'message' => 'Paiement en cours de confirmation, merci de patienter.'];
        }

        if (!$this->fedapay->isSuccessful($transactionData)) {
            $paiement->update([
                'status'                 => PaiementStatus::ECHOUE->value,
                'fedapay_transaction_id' => $transactionId,
                'fedapay_response'       => $transactionData,
                'raison_echec'           => $transactionData['message'] ?? 'Paiement non abouti',
            ]);

            throw ValidationException::withMessages([
                'paiement' => ['Le paiement n\'a pas abouti. Veuillez réessayer.'],
            ]);
        }

        return DB::transaction(function () use ($user, $paiement, $transactionId, $transactionData) {
            $paiement->update([
                'status'                 => PaiementStatus::SUCCES->value,
                'fedapay_transaction_id' => $transactionId,
                'fedapay_response'       => $transactionData,
                'telephone_paiement'     => $this->fedapay->getPhone($transactionData),
                'paye_le'                => now(),
            ]);

            $paiement->property->update(['status' => PropertyStatus::LOUE->value]);

            Log::info('Logement réservé', [
                'user_id'     => $user->id,
                'property_id' => $paiement->id_property,
            ]);

            return [
                'property_id'    => $paiement->property->id,
                'property_title' => $paiement->property->title,
                'message'        => 'Réservation confirmée. Le propriétaire va vous contacter pour finaliser la location.',
            ];
        });
    }
}
