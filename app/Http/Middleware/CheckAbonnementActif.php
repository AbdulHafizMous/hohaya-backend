<?php

namespace App\Http\Middleware;

use App\Enums\AbonnementStatus;
use App\Enums\AbonnementType;
use App\Models\Abonnement;
use App\Models\Setting;
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

        // L'admin peut désactiver l'obligation d'abonnement (ex: pendant la phase gratuite/beta).
        $abonnementRequis = filter_var(Setting::get('abonnement_requis', '1'), FILTER_VALIDATE_BOOLEAN);
        if (!$abonnementRequis) {
            return $next($request);
        }

        // Quand l'abonnement est obligatoire, le plan Gratuit ne compte jamais comme suffisant —
        // seul un plan payant permet de publier.
        $abonnementActif = Abonnement::where('id_user', $user->id)
            ->where('status', AbonnementStatus::ACTIF->value)
            ->where('date_fin', '>', now())
            ->where('type', '!=', AbonnementType::GRATUIT->value)
            ->exists();

        if (!$abonnementActif) {
            return response()->json([
                'success' => false,
                'message' => 'Un abonnement payant actif est requis pour publier des annonces. Abonnez-vous via POST /api/abonnements/initier.',
                'data'    => [
                    'tarifs_url' => '/api/abonnements/tarifs',
                ],
            ], 403);
        }

        return $next($request);
    }
}