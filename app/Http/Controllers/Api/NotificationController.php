<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpClientCache;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;


#[OA\Tag(name: "Notification", description: "API Endpoints for user notification")]
class NotificationController extends Controller
{
    private string $projectId = 'grand-public-f4fd5';
    private string $fcmUrl;

    // Service Account — à déplacer dans un fichier JSON sécurisé
    // storage/app/service-account.json (hors du repo Git)
    private array $serviceAccount;

    public function __construct()
    {
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Charge depuis le fichier JSON sécurisé
        $path = storage_path('app/firebase-service-account.json');
        $this->serviceAccount = json_decode(file_get_contents($path), true);
    }

    // ════════════════════════════════════════════════════════════════════════
    // OBTENIR LE TOKEN D'ACCÈS OAUTH2
    // ════════════════════════════════════════════════════════════════════════
    private function getAccessToken(): string
    {
        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $this->serviceAccount
        );

        $httpHandler = HttpHandlerFactory::build(new \GuzzleHttp\Client());
        $token = $credentials->fetchAuthToken($httpHandler);

        return $token['access_token'];
    }

    // ════════════════════════════════════════════════════════════════════════
    // ENVOYER UNE NOTIFICATION
    // ════════════════════════════════════════════════════════════════════════

    #[OA\Post(
    path: "/api/notifications/send",
    summary: "Envoyer une notification push FCM",
    description: "Envoie une notification push à un appareil via Firebase Cloud Messaging",
    security: [["sanctum" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["fcm_token", "title", "body"],
            properties: [
                new OA\Property(property: "fcm_token", type: "string", description: "Token FCM de l'appareil destinataire", example: "eXXXX..."),
                new OA\Property(property: "title", type: "string", description: "Titre de la notification", example: "Nouvelle commande"),
                new OA\Property(property: "body", type: "string", description: "Corps de la notification", example: "Votre commande a été confirmée"),
                new OA\Property(property: "data", type: "object", description: "Données supplémentaires (optionnel)", example: ["route" => "/orders/123", "order_id" => "123"])
            ]
        )
    ),
    tags: ["Notifications"],
    responses: [
        new OA\Response(
            response: 200,
            description: "Notification envoyée avec succès",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Notification envoyée avec succès"),
                    new OA\Property(property: "fcm_response", type: "object")
                ]
            )
        ),
        new OA\Response(response: 422, description: "Erreur de validation"),
        new OA\Response(response: 500, description: "Erreur serveur FCM")
    ]
)]
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'title'     => 'required|string|max:255',
            'body'      => 'required|string|max:1000',
            'data'      => 'nullable|array',
        ], [
            'fcm_token.required' => 'Le token FCM est requis.',
            'title.required'     => 'Le titre est requis.',
            'body.required'      => 'Le message est requis.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $accessToken = $this->getAccessToken();
            $result = $this->sendNotification(
                token:       $request->fcm_token,
                title:       $request->title,
                body:        $request->body,
                data:        $request->data ?? [],
                accessToken: $accessToken
            );

            return response()->json([
                'message'      => 'Notification envoyée avec succès',
                'fcm_response' => $result,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'envoi de la notification',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // ENVOYER À PLUSIEURS TOKENS (broadcast)
    // ════════════════════════════════════════════════════════════════════════

    
#[OA\Post(
    path: "/api/notifications/broadcast",
    summary: "Envoyer une notification à plusieurs appareils",
    security: [["sanctum" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["fcm_tokens", "title", "body"],
            properties: [
                new OA\Property(
                    property: "fcm_tokens",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["token1", "token2"]
                ),
                new OA\Property(property: "title", type: "string", example: "Promotion"),
                new OA\Property(property: "body", type: "string", example: "Offre spéciale disponible !"),
                new OA\Property(property: "data", type: "object", example: ["route" => "/promotions"])
            ]
        )
    ),
    tags: ["Notifications"],
    responses: [
        new OA\Response(
            response: 200,
            description: "Notifications envoyées",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "sent", type: "integer", example: 5),
                    new OA\Property(property: "failed", type: "integer", example: 1)
                ]
            )
        )
    ]
)]
    public function broadcast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_tokens'   => 'required|array|min:1',
            'fcm_tokens.*' => 'required|string',
            'title'        => 'required|string|max:255',
            'body'         => 'required|string|max:1000',
            'data'         => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $accessToken = $this->getAccessToken();
            $sent = 0;
            $failed = 0;

            foreach ($request->fcm_tokens as $token) {
                try {
                    $this->sendNotification(
                        token:       $token,
                        title:       $request->title,
                        body:        $request->body,
                        data:        $request->data ?? [],
                        accessToken: $accessToken
                    );
                    $sent++;
                } catch (Exception $e) {
                    $failed++;
                    Log::error("FCM send failed for token $token: " . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Broadcast terminé',
                'sent'    => $sent,
                'failed'  => $failed,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du broadcast',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // MÉTHODE PRIVÉE — appel HTTP à FCM
    // ════════════════════════════════════════════════════════════════════════
    private function sendNotification(
        string $token,
        string $title,
        string $body,
        array  $data,
        string $accessToken
    ): array {
        $payload = [
            'message' => [
                'token'        => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data'    => array_map('strval', $data), // FCM exige des strings
                'android' => [
                    'priority'     => 'HIGH',
                    'notification' => [
                        'sound'      => 'default',
                        'channel_id' => 'high_importance_channel',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->post($this->fcmUrl, $payload);

        if (!$response->successful()) {
            throw new Exception(
                'FCM error [' . $response->status() . ']: ' . $response->body()
            );
        }

        return $response->json();
    }
}