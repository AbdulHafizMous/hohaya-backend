<?php

namespace App\Http\Controllers\Api;

use App\Enums\AbonnementStatus;
use App\Enums\AbonnementType;
use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Services\FedaPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FedaPayWebhookController extends Controller
{
    public function __construct(private FedaPayService $fedapay) {}

    /**
     * Webhook appelé par FedaPay pour notifier l'état d'une transaction.
     * Le payload webhook (événement "object_id" + type) n'est utilisé que pour
     * identifier la transaction ; le statut réel est toujours re-vérifié auprès
     * de l'API FedaPay (GET /v1/transactions/{id}) plutôt que d'être fait confiance
     * au contenu du webhook, pour éviter toute falsification.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-FEDAPAY-SIGNATURE', '');

        if (!$this->fedapay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('FedaPay webhook signature invalide', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $data = $request->all();
        // Forme observée des événements FedaPay : { id, type, entity, object_id, object }
        $transactionId = $data['object_id'] ?? $data['entity']['id'] ?? null;
        $eventType     = $data['type'] ?? null;

        Log::info('FedaPay webhook reçu', [
            'transaction_id' => $transactionId,
            'event'          => $eventType,
        ]);

        if (!$transactionId) {
            return response()->json(['message' => 'Transaction ID manquant.'], 400);
        }

        $paiement = Paiement::where('fedapay_transaction_id', $transactionId)->first();

        if (!$paiement) {
            Log::warning('FedaPay webhook: paiement introuvable', ['transaction_id' => $transactionId]);
            return response()->json(['message' => 'OK'], 200); // On retourne 200 pour ne pas que FedaPay retry
        }

        if ($paiement->status !== PaiementStatus::EN_ATTENTE->value) {
            return response()->json(['message' => 'OK'], 200); // Déjà traité (par /confirmer ou un précédent webhook)
        }

        // Toujours re-vérifier auprès de l'API plutôt que de faire confiance au payload du webhook
        $transactionData = $this->fedapay->verifyTransaction((string) $transactionId);

        if ($this->fedapay->isSuccessful($transactionData)) {
            $paiement->update([
                'status'           => PaiementStatus::SUCCES->value,
                'fedapay_response' => $transactionData,
                'paye_le'          => now(),
            ]);

            if ($paiement->type === PaiementType::ABONNEMENT->value && $paiement->abonnement) {
                $typeEnum = AbonnementType::from($paiement->abonnement->type);
                $dateFin  = now()->addDays($typeEnum->dureeEnJours());

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
        } elseif (in_array($transactionData['status'] ?? null, ['declined', 'canceled'])) {
            $paiement->update([
                'status'           => PaiementStatus::ECHOUE->value,
                'fedapay_response' => $transactionData,
                'raison_echec'     => $transactionData['message'] ?? 'Paiement échoué',
            ]);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}
