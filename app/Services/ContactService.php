<?php

namespace App\Services;

use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use App\Models\AccessContact;
use App\Models\Paiement;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ContactDebloqueNotification;
use App\Notifications\ContactDebloqueParChercheurNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(private FedaPayService $fedapay, private AuthService $authService) {}

    /**
     * Trouver ou créer un compte chercheur à partir des infos fournies au moment du paiement,
     * sans passer par une inscription classique. L'utilisateur pourra définir un mot de passe plus tard.
     */
    public function findOrCreateGuest(array $data): User // Also used by ReservationService for guest reservations
    {
        $user = User::where(function ($q) use ($data) {
            if (!empty($data['email'])) $q->where('email', $data['email']);
            if (!empty($data['phone'])) $q->orWhere('phone', $data['phone']);
        })->first();

        if ($user) {
            return $user;
        }

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'] ?? null,
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole('seeker');

        return $user;
    }

    /**
     * Initier le déblocage d'un contact pour un visiteur non connecté : crée/retrouve son compte
     * et retourne un token de session en plus des infos de paiement, pour le connecter automatiquement.
     */
    public function initierDeblocageInvite(array $data): array
    {
        $user = $this->findOrCreateGuest($data);
        $tokens = $this->authService->issueTokensFor($user);
        $result = $this->initierDeblocage($user, $data['property_id']);

        return array_merge($tokens, $result);
    }

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

        $prix = (float) Setting::get('deblocage_contact_prix', (string) config('fedapay.deblocage_prix', 500));

        // Essais gratuits : les N premiers déblocages du chercheur (toutes annonces confondues)
        // sont gratuits, N étant configurable par l'admin. Le prix à 0 débloque aussi directement.
        $essaisGratuits = (int) Setting::get('deblocage_essais_gratuits', '3');
        $deblocagesExistants = AccessContact::where('id_user', $user->id)->count();
        $estGratuit = $prix <= 0 || $deblocagesExistants < $essaisGratuits;

        if ($estGratuit) {
            return DB::transaction(function () use ($user, $property, $prix) {
                $paiement = Paiement::create([
                    'id_user'           => $user->id,
                    'type'              => PaiementType::DEBLOCAGE->value,
                    'status'            => PaiementStatus::SUCCES->value,
                    'montant'           => 0,
                    'part_hohaya'       => 0,
                    'part_proprietaire' => 0,
                    'devise'            => 'XOF',
                    'id_property'       => $property->id,
                    'paye_le'           => now(),
                    'created_by'        => $user->id,
                ]);

                AccessContact::create([
                    'id_user'     => $user->id,
                    'id_property' => $property->id,
                    'id_paiement' => $paiement->id,
                ]);

                $this->notifierDeblocage($user, $property);

                return $this->getContactInfo($user, $property, nouveau: true);
            });
        }

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
            'fedapay_public_key' => config('fedapay.public_key'),
            'sandbox'            => config('fedapay.sandbox'),
            'instructions'       => 'Payez ' . $prix . ' XOF via FedaPay pour accéder au contact du propriétaire. Appelez ensuite POST /api/contacts/confirmer.',
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

        // Vérifier FedaPay
        $transactionData = $this->fedapay->verifyTransaction($transactionId);

        if ($this->fedapay->isPending($transactionData)) {
            // Mobile Money : le client valide via USSD après la fermeture du widget.
            // Ce n'est pas un échec, on garde le paiement en attente pour permettre un nouvel essai.
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
            // Répartition du paiement entre Hohaya et le propriétaire, taux configurable en admin (Setting).
            $pourcentageHohaya = (float) Setting::get('deblocage_part_hohaya_pourcentage', '50');
            $partHohaya        = round($paiement->montant * $pourcentageHohaya / 100, 2);
            $partProprietaire  = round($paiement->montant - $partHohaya, 2);

            $paiement->update([
                'status'                 => PaiementStatus::SUCCES->value,
                'fedapay_transaction_id' => $transactionId,
                'fedapay_response'       => $transactionData,
                'telephone_paiement'     => $this->fedapay->getPhone($transactionData),
                'paye_le'                => now(),
                'part_hohaya'            => $partHohaya,
                'part_proprietaire'      => $partProprietaire,
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

            $this->notifierDeblocage($user, $paiement->property);

            return $this->getContactInfo($user, $paiement->property);
        });
    }

    /**
     * Notifie le chercheur (coordonnées du propriétaire) et le propriétaire (chercheur intéressé)
     * après un déblocage de contact, qu'il soit payant ou gratuit.
     */
    private function notifierDeblocage(User $user, Property $property): void
    {
        if ($user->email) {
            try {
                $user->notify(new ContactDebloqueNotification($property, $property->proprietaire));
            } catch (\Throwable $e) {
                // L'envoi de l'email ne doit jamais faire échouer le déblocage du contact déjà payé.
                Log::error('Échec envoi email contact débloqué', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $proprietaire = $property->proprietaire;
        if ($proprietaire?->email) {
            try {
                $proprietaire->notify(new ContactDebloqueParChercheurNotification($property, $user));
            } catch (\Throwable $e) {
                Log::error('Échec envoi email propriétaire (contact débloqué)', [
                    'proprietaire_id' => $proprietaire->id,
                    'message'         => $e->getMessage(),
                ]);
            }
        }
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
     * Chercheurs ayant débloqué le contact d'un propriétaire, toutes annonces confondues.
     */
    public function chercheursInteresses(User $proprietaire, int $perPage = 15)
    {
        return AccessContact::with(['user', 'property.mediaPrincipal'])
            ->whereHas('property', fn($q) => $q->where('id_user', $proprietaire->id))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Retourner les informations de contact du propriétaire
     */
    private function getContactInfo(User $user, Property $property, bool $nouveau = false): array
    {
        $proprietaire = $property->proprietaire;

        return [
            'access'        => true,
            // Distingue "vient d'être débloqué à l'instant" (affiche le popup de succès côté
            // frontend) de "accès déjà existant" (juste réafficher les coordonnées).
            'nouveau'       => $nouveau,
            'property_id'   => $property->id,
            'property_title' => $property->title,
            'lien_maps'     => $property->lien_maps,
            'proprietaire'  => [
                'nom'       => $proprietaire->name,
                'telephone' => $proprietaire->phone,
                'email'     => $proprietaire->email,
            ],
            'message'       => 'Contact débloqué. Vous pouvez maintenant contacter directement le propriétaire.',
        ];
    }
}