<?php

namespace App\Services;

use App\Enums\AbonnementStatus;
use App\Enums\AbonnementType;
use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use App\Models\Abonnement;
use App\Models\Paiement;
use App\Models\TarifAbonnement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AbonnementService
{
    public function __construct(private KKiaPayService $kkiapay) {}

    /**
     * Récupérer les tarifs disponibles
     */
    public function getTarifs(): array
    {
        return TarifAbonnement::where('is_actif', true)
            ->orderBy('montant')
            ->get()
            ->map(fn(TarifAbonnement $t) => $this->formatTarif($t))
            ->toArray();
    }

    /**
     * Récupérer l'abonnement actif d'un utilisateur
     */
    public function getAbonnementActif(User $user): ?Abonnement
    {
        return Abonnement::where('id_user', $user->id)
            ->where('status', AbonnementStatus::ACTIF->value)
            ->where('date_fin', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Historique des abonnements
     */
    public function historique(User $user, int $perPage = 15)
    {
        return Abonnement::with('paiement')
            ->where('id_user', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Initier un abonnement (avant paiement)
     * Crée l'abonnement en attente + le paiement en attente
     */
    public function initier(User $user, string $type): array
    {
        if (!in_array($type, AbonnementType::values())) {
            throw ValidationException::withMessages([
                'type' => ['Type d\'abonnement invalide.'],
            ]);
        }

        $tarif = TarifAbonnement::where('type', $type)->where('is_actif', true)->first();

        if (!$tarif) {
            throw ValidationException::withMessages([
                'type' => ['Ce type d\'abonnement n\'est pas disponible actuellement.'],
            ]);
        }

        // Vérifier s'il y a déjà un abonnement actif
        $actif = $this->getAbonnementActif($user);
        if ($actif) {
            throw ValidationException::withMessages([
                'abonnement' => [
                    'Vous avez déjà un abonnement actif jusqu\'au ' . $actif->date_fin->format('d/m/Y') . '.'
                ],
            ]);
        }

        return DB::transaction(function () use ($user, $type, $tarif) {
            // Créer le paiement en attente
            $paiement = Paiement::create([
                'id_user'   => $user->id,
                'type'      => PaiementType::ABONNEMENT->value,
                'status'    => PaiementStatus::EN_ATTENTE->value,
                'montant'   => $tarif->montant,
                'devise'    => $tarif->devise,
                'created_by' => $user->id,
            ]);

            // Créer l'abonnement en attente
            $abonnement = Abonnement::create([
                'id_user'     => $user->id,
                'type'        => $type,
                'status'      => AbonnementStatus::EN_ATTENTE->value,
                'montant'     => $tarif->montant,
                'devise'      => $tarif->devise,
                'id_paiement' => $paiement->id,
                'created_by'  => $user->id,
            ]);

            // Lier le paiement à l'abonnement
            $paiement->update(['id_abonnement' => $abonnement->id]);

            return [
                'abonnement'    => $this->formatAbonnement($abonnement),
                'paiement_id'   => $paiement->id,
                'montant'       => $tarif->montant,
                'devise'        => $tarif->devise,
                'kkiapay_public_key' => config('kkiapay.public_key'),
                'sandbox'       => config('kkiapay.sandbox'),
                'instructions'  => 'Utilisez le SDK KKiaPay avec la public_key fournie pour finaliser le paiement. Après paiement, appelez POST /api/abonnements/confirmer avec le transaction_id KKiaPay.',
            ];
        });
    }

    /**
     * Confirmer un abonnement après paiement KKiaPay réussi
     */
    public function confirmer(User $user, int $paiementId, string $transactionId): array
    {
        $paiement = Paiement::where('id', $paiementId)
            ->where('id_user', $user->id)
            ->where('status', PaiementStatus::EN_ATTENTE->value)
            ->first();

        if (!$paiement) {
            throw ValidationException::withMessages([
                'paiement' => ['Paiement introuvable ou déjà traité.'],
            ]);
        }

        // Vérifier la transaction auprès de KKiaPay
        $transactionData = $this->kkiapay->verifyTransaction($transactionId);

        if (!$this->kkiapay->isSuccessful($transactionData)) {
            // Marquer comme échoué
            $paiement->update([
                'status'               => PaiementStatus::ECHOUE->value,
                'kkiapay_transaction_id' => $transactionId,
                'kkiapay_response'     => $transactionData,
                'raison_echec'         => $transactionData['message'] ?? 'Paiement non abouti',
            ]);

            throw ValidationException::withMessages([
                'paiement' => ['Le paiement n\'a pas abouti. Veuillez réessayer.'],
            ]);
        }

        // Vérifier que le montant correspond
        $montantKkiapay = $this->kkiapay->getAmount($transactionData);
        if ($montantKkiapay < $paiement->montant) {
            Log::warning('KKiaPay montant insuffisant', [
                'attendu'  => $paiement->montant,
                'recu'     => $montantKkiapay,
                'user_id'  => $user->id,
            ]);

            throw ValidationException::withMessages([
                'paiement' => ['Montant du paiement insuffisant.'],
            ]);
        }

        return DB::transaction(function () use ($user, $paiement, $transactionId, $transactionData) {
            $typeEnum   = AbonnementType::from($paiement->abonnement->type);
            $dateDebut  = now();
            $dateFin    = now()->addDays($typeEnum->dureeEnJours());

            // Mettre à jour le paiement
            $paiement->update([
                'status'                 => PaiementStatus::SUCCES->value,
                'kkiapay_transaction_id' => $transactionId,
                'kkiapay_reference'      => $transactionData['reference'] ?? null,
                'kkiapay_response'       => $transactionData,
                'telephone_paiement'     => $this->kkiapay->getPhone($transactionData),
                'paye_le'                => now(),
            ]);

            // Activer l'abonnement
            $paiement->abonnement->update([
                'status'     => AbonnementStatus::ACTIF->value,
                'date_debut' => $dateDebut,
                'date_fin'   => $dateFin,
                'updated_by' => $user->id,
            ]);

            // Mettre à jour le user
            $user->update([
                'is_suscribed'       => true,
                'subscription_start' => $dateDebut,
                'subscription_end'   => $dateFin,
            ]);

            Log::info('Abonnement activé', [
                'user_id'        => $user->id,
                'abonnement_id'  => $paiement->abonnement->id,
                'date_fin'       => $dateFin,
            ]);

            return [
                'abonnement' => $this->formatAbonnement($paiement->abonnement->fresh()),
                'paiement'   => $this->formatPaiement($paiement->fresh()),
                'message'    => 'Abonnement activé avec succès jusqu\'au ' . $dateFin->format('d/m/Y') . '.',
            ];
        });
    }

    /**
     * Vérifier les abonnements expirés (appelé par un job schedulé)
     */
    public function verifierExpirations(): int
    {
        $expires = Abonnement::where('status', AbonnementStatus::ACTIF->value)
            ->where('date_fin', '<', now())
            ->get();

        $count = 0;
        foreach ($expires as $abonnement) {
            /** @var Abonnement $abonnement */
            $abonnement->update(['status' => AbonnementStatus::EXPIRE->value]);

            // Mettre à jour le user
            $abonnement->user->update([
                'is_suscribed' => false,
            ]);

            $count++;
        }

        Log::info("Abonnements expirés traités: {$count}");
        return $count;
    }

    public function formatAbonnement(Abonnement $abonnement): array
    {
        $typeEnum = AbonnementType::tryFrom($abonnement->type);
        return [
            'id'             => $abonnement->id,
            'type'           => $abonnement->type,
            'type_label'     => $typeEnum?->label() ?? $abonnement->type,
            'status'         => $abonnement->status,
            'montant'        => $abonnement->montant,
            'devise'         => $abonnement->devise,
            'date_debut'     => $abonnement->date_debut?->format('d/m/Y'),
            'date_fin'       => $abonnement->date_fin?->format('d/m/Y'),
            'jours_restants' => $abonnement->joursRestants(),
            'est_actif'      => $abonnement->isActif(),
            'auto_renew'     => $abonnement->auto_renew,
            'created_at'     => $abonnement->created_at,
        ];
    }

    public function formatPaiement(Paiement $paiement): array
    {
        return [
            'id'                     => $paiement->id,
            'type'                   => $paiement->type,
            'status'                 => $paiement->status,
            'montant'                => $paiement->montant,
            'devise'                 => $paiement->devise,
            'kkiapay_transaction_id' => $paiement->kkiapay_transaction_id,
            'telephone_paiement'     => $paiement->telephone_paiement,
            'operateur'              => $paiement->operateur,
            'paye_le'                => $paiement->paye_le?->format('d/m/Y H:i'),
            'created_at'             => $paiement->created_at,
        ];
    }

    private function formatTarif(TarifAbonnement $tarif): array
    {
        $typeEnum = AbonnementType::tryFrom($tarif->type);
        return [
            'type'        => $tarif->type,
            'label'       => $typeEnum?->label() ?? $tarif->type,
            'montant'     => $tarif->montant,
            'devise'      => $tarif->devise,
            'description' => $tarif->description,
        ];
    }
}