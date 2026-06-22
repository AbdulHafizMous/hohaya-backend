<?php

namespace App\Console\Commands;

use App\Services\AbonnementService;
use Illuminate\Console\Command;

class VerifierAbonnementsExpires extends Command
{
    protected $signature   = 'hohaya:check-abonnements';
    protected $description = 'Vérifier et marquer les abonnements expirés';

    public function handle(AbonnementService $abonnementService): void
    {
        $count = $abonnementService->verifierExpirations();
        $this->info("✅ {$count} abonnement(s) expiré(s) traités.");
    }
}
