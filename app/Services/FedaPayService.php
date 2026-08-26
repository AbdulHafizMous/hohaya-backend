<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FedaPayService
{
    private string $baseUrl;
    private string $secretKey;
    private ?string $webhookSecret;
    private bool   $sandbox;

    public function __construct()
    {
        $this->baseUrl       = config('fedapay.base_url', 'https://sandbox-api.fedapay.com');
        $this->secretKey     = config('fedapay.secret_key');
        $this->webhookSecret = config('fedapay.webhook_secret');
        $this->sandbox       = config('fedapay.sandbox', true);
    }

    /**
     * Vérifier une transaction FedaPay après callback frontend (widget Checkout.js)
     */
    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->get("{$this->baseUrl}/v1/transactions/{$transactionId}");

            if ($response->failed()) {
                Log::error('FedaPay verify failed', [
                    'transaction_id' => $transactionId,
                    'status'         => $response->status(),
                    'body'           => $response->body(),
                ]);

                throw ValidationException::withMessages([
                    'transaction' => ['Impossible de vérifier la transaction FedaPay.'],
                ]);
            }

            // L'API FedaPay enveloppe la ressource sous la clé "v1/transaction"
            $data = $response->json('v1/transaction') ?? $response->json();

            Log::info('FedaPay transaction verified', [
                'transaction_id' => $transactionId,
                'status'         => $data['status'] ?? 'unknown',
            ]);

            return $data;

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('FedaPay exception', ['message' => $e->getMessage()]);
            throw ValidationException::withMessages([
                'transaction' => ['Erreur de connexion au service de paiement.'],
            ]);
        }
    }

    /**
     * Vérifier la signature d'un webhook FedaPay.
     * FedaPay envoie l'en-tête "FedaPay-Signature" au format "t=<timestamp>,s=<signature>"
     * où la signature = hmac_sha256(webhook_secret, "<timestamp>.<payload>")
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        if (!$this->webhookSecret) return false;

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key !== null) $parts[trim($key)] = trim((string) $value);
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['s'] ?? null;

        if (!$timestamp || !$signature) return false;

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Vérifier si une transaction est bien un succès
     */
    public function isSuccessful(array $transactionData): bool
    {
        return ($transactionData['status'] ?? '') === 'approved';
    }

    /**
     * Une transaction Mobile Money est souvent asynchrone : l'utilisateur valide via USSD
     * après la fermeture du widget, donc le statut peut rester "pending" un court instant
     * après le retour du checkout. Ce n'est pas un échec — il faut réessayer la vérification.
     */
    public function isPending(array $transactionData): bool
    {
        return in_array($transactionData['status'] ?? '', ['pending', 'waiting_for_customer_validation'], true);
    }

    /**
     * Vrai échec (refusé/annulé) — distinct d'un simple "pending".
     */
    public function isFailed(array $transactionData): bool
    {
        return in_array($transactionData['status'] ?? '', ['declined', 'canceled'], true);
    }

    /**
     * Extraire le montant d'une transaction
     */
    public function getAmount(array $transactionData): float
    {
        return (float) ($transactionData['amount'] ?? 0);
    }

    /**
     * Extraire le téléphone d'une transaction
     */
    public function getPhone(array $transactionData): ?string
    {
        return $transactionData['customer']['phone_number']['number'] ?? null;
    }
}
