<?php

namespace App\Http\Middleware;

use App\Enums\AbonnementStatus;
use App\Models\Abonnement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAbonnementActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.',
                'data'    => null,
            ], 401);
        }

        $abonnementActif = Abonnement::where('id_user', $user->id)
            ->where('status', AbonnementStatus::ACTIF->value)
            ->where('date_fin', '>', now())
            ->exists();

        if (!$abonnementActif) {
            return response()->json([
                'success' => false,
                'message' => 'Un abonnement actif est requis pour publier des annonces. Abonnez-vous via POST /api/abonnements/initier.',
                'data'    => [
                    'tarifs_url' => '/api/abonnements/tarifs',
                ],
            ], 403);
        }

        return $next($request);
    }
}