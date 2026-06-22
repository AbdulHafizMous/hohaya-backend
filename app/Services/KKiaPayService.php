<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KKiaPayService
{
    private string $baseUrl;
    private string $privateKey;
    private string $secretKey;
    private bool   $sandbox;

    public function __construct()
    {
        $this->baseUrl    = config('kkiapay.base_url', 'https://api.kkiapay.me');
        $this->privateKey = config('kkiapay.private_key');
        $this->secretKey  = config('kkiapay.secret_key');
        $this->sandbox    = config('kkiapay.sandbox', true);
    }

    /**
     * Vérifier une transaction KKiaPay après callback frontend
     */
    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'x-private-key' => $this->privateKey,
                'Content-Type'  => 'application/json',
            ])->get("{$this->baseUrl}/api/v1/transactions/{$transactionId}/status");

            if ($response->failed()) {
                Log::error('KKiaPay verify failed', [
                    'transaction_id' => $transactionId,
                    'status'         => $response->status(),
                    'body'           => $response->body(),
                ]);

                throw ValidationException::withMessages([
                    'transaction' => ['Impossible de vérifier la transaction KKiaPay.'],
                ]);
            }

            $data = $response->json();

            Log::info('KKiaPay transaction verified', [
                'transaction_id' => $transactionId,
                'status'         => $data['status'] ?? 'unknown',
            ]);

            return $data;

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('KKiaPay exception', ['message' => $e->getMessage()]);
            throw ValidationException::withMessages([
                'transaction' => ['Erreur de connexion au service de paiement.'],
            ]);
        }
    }

    /**
     * Vérifier la signature d'un webhook KKiaPay
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($expected, $signature);
    }

    /**
     * Vérifier si une transaction est bien un succès
     */
    public function isSuccessful(array $transactionData): bool
    {
        return ($transactionData['status'] ?? '') === 'SUCCESS';
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
        return $transactionData['phone'] ?? null;
    }
}