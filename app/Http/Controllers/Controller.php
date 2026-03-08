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
    //
}