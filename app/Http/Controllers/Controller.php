<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Hohaya API",
    description: "API documentation for Hohaya platform"
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Local API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "Sanctum"
)]
abstract class Controller
{
     /**
     * Send a standardized API response.
     *
     * @param  mixed  $data  The data to include in the response.
     * @param  string|null  $message  An optional message to include in the response.
     * @param  bool  $success  Indicates whether the request was successful (default is true).
     * @param  int  $status  The HTTP status code for the response (default is 200).
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendApiResponse($data = null, $message = null, $success = true, $status = 200)
    {
        return response()->json([
            'success' => $success,
            'data' => $data,
            'message' => $message,
        ], $status);
    }
}