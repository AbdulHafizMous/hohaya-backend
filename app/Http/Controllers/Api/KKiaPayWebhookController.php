<?php

namespace App\Http\Controllers\Api;

use App\Enums\AbonnementStatus;
use App\Enums\AbonnementType;
use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Services\KKiaPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KKiaPayWebhookController extends Controller
{
    public function __construct(private KKiaPayService $kkiapay) {}

    /**
     * Webhook appelé par KKiaPay pour notifier l'état d'une transaction
     */
    public function handle(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('x-kkiapay-signature', '');

        // Vérifier la signature
        if (!$this->kkiapay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('KKiaPay webhook signature invalide', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $data          = $request->all();
        $transactionId = $data['transactionId'] ?? null;
        $status        = $data['status'] ?? null;

        Log::info('KKiaPay webhook reçu', [
            'transaction_id' => $transactionId,
            'status'         => $status,
        ]);

        if (!$transactionId) {
            return response()->json(['message' => 'Transaction ID manquant.'], 400);
        }

        // Trouver le paiement correspondant
        $paiement = Paiement::where('kkiapay_transaction_id', $transactionId)
            ->orWhere(function ($q) use ($data) {
                // Fallback sur référence si disponible
                if (!empty($data['reference'])) {
                    $q->where('kkiapay_reference', $data['reference']);
                }
            })
            ->first();

        if (!$paiement) {
            Log::warning('KKiaPay webhook: paiement introuvable', ['transaction_id' => $transactionId]);
            return response()->json(['message' => 'OK'], 200); // On retourne 200 pour ne pas que KKiaPay retry
        }

        if ($status === 'SUCCESS' && $paiement->status !== PaiementStatus::SUCCES->value) {
            $paiement->update([
                'status'           => PaiementStatus::SUCCES->value,
                'kkiapay_response' => $data,
                'paye_le'          => now(),
            ]);

            // Activer abonnement si c'est un paiement d'abonnement
            if ($paiement->type === PaiementType::ABONNEMENT->value && $paiement->abonnement) {
                $typeEnum  = AbonnementType::from($paiement->abonnement->type);
                $dateFin   = now()->addDays($typeEnum->dureeEnJours());

                $paiement->abonnement->update([
                    'status'     => AbonnementStatus::ACTIF->value,
                    'date_debut' => now(),
                    'date_fin'   => $dateFin,
                ]);

                $paiement->abonnement->user->update([
                    'is_suscribed'       => true,
                    'subscription_start' => now(),
                    'subscription_end'   => $dateFin,
                ]);
            }
        } elseif ($status === 'FAILED') {
            $paiement->update([
                'status'           => PaiementStatus::ECHOUE->value,
                'kkiapay_response' => $data,
                'raison_echec'     => $data['message'] ?? 'Paiement échoué',
            ]);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}