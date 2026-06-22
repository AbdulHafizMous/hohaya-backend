<?php

namespace App\Services;

use App\Enums\AbonnementStatus;
use App\Enums\PaiementStatus;
use App\Enums\PropertyStatus;
use App\Models\Abonnement;
use App\Models\Paiement;
use App\Models\Property;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AdminService
{
    public function dashboard(): array
    {
        $now = now();

        return [
            'users' => [
                'total'         => User::count(),
                'proprietaires' => User::role('owner')->count(),
                'chercheurs'    => User::role('seeker')->count(),
                'nouveaux_30j'  => User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
                'verifies'      => User::where('is_verified', true)->count(),
            ],
            'properties' => [
                'total'         => Property::count(),
                'disponibles'   => Property::where('status', PropertyStatus::DISPONIBLE->value)->count(),
                'verifiees'     => Property::where('is_verified', true)->count(),
                'en_attente'    => Property::where('is_verified', false)->whereNull('deleted_at')->count(),
                'nouvelles_30j' => Property::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            ],
            'abonnements' => [
                'actifs'     => Abonnement::where('status', AbonnementStatus::ACTIF->value)
                    ->where('date_fin', '>', $now)->count(),
                'expires_7j' => Abonnement::where('status', AbonnementStatus::ACTIF->value)
                    ->where('date_fin', '<=', $now->copy()->addDays(7))
                    ->where('date_fin', '>', $now)->count(),
            ],
            'revenus' => [
                'total'      => (float) Paiement::where('status', PaiementStatus::SUCCES->value)->sum('montant'),
                'ce_mois'    => (float) Paiement::where('status', PaiementStatus::SUCCES->value)
                    ->whereMonth('paye_le', $now->month)
                    ->whereYear('paye_le', $now->year)
                    ->sum('montant'),
                'mois_dernier' => (float) Paiement::where('status', PaiementStatus::SUCCES->value)
                    ->whereMonth('paye_le', $now->copy()->subMonth()->month)
                    ->whereYear('paye_le', $now->copy()->subMonth()->year)
                    ->sum('montant'),
            ],
            'support' => [
                'ouverts'  => SupportTicket::where('status', 'ouvert')->count(),
                'en_cours' => SupportTicket::where('status', 'en_cours')->count(),
            ],
            'paiements' => [
                'en_attente' => Paiement::where('status', PaiementStatus::EN_ATTENTE->value)->count(),
                'echoues_7j' => Paiement::where('status', PaiementStatus::ECHOUE->value)
                    ->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            ],
        ];
    }

    public function revenusParMois(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date   = now()->subMonths($i);
            $data[] = [
                'mois'    => $date->format('M Y'),
                'montant' => (float) Paiement::where('status', PaiementStatus::SUCCES->value)
                    ->whereMonth('paye_le', $date->month)
                    ->whereYear('paye_le', $date->year)
                    ->sum('montant'),
            ];
        }
        return $data;
    }

    public function listeUtilisateurs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = User::withTrashed()->with('roles');

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }
        if (isset($filters['is_verified'])) {
            $query->where('is_verified', (bool) $filters['is_verified']);
        }
        if (!empty($filters['deleted'])) {
            $query->onlyTrashed();
        }

        return $query->latest()->paginate($perPage);
    }

    public function verifierProprietaire(int $userId, bool $verified): User
    {
        $user = User::find($userId);

        if (!$user) {
            throw ValidationException::withMessages(['user' => ['Utilisateur introuvable.']]);
        }

        if (!$user->hasRole('owner')) {
            throw ValidationException::withMessages(['user' => ["Cet utilisateur n'est pas un propriétaire."]]);
        }

        $user->update(['is_verified' => $verified]);
        return $user->fresh();
    }

    public function toggleSuspension(int $userId, bool $suspendre): User
    {
        $user = User::withTrashed()->find($userId);

        if (!$user) {
            throw ValidationException::withMessages(['user' => ['Utilisateur introuvable.']]);
        }

        if ($suspendre) {
            $user->tokens()->delete();
            $user->delete();
        } else {
            $user->restore();
        }

        return $user->fresh();
    }

    public function annoncesEnAttente(int $perPage = 20): LengthAwarePaginator
    {
        return Property::with(['proprietaire', 'mediaPrincipal'])
            ->where('is_verified', false)
            ->whereNull('deleted_at')
            ->latest()
            ->paginate($perPage);
    }

    public function exportPaiementsCSV(): string
    {
        $paiements = Paiement::with('user')
            ->where('status', PaiementStatus::SUCCES->value)
            ->orderBy('paye_le', 'desc')
            ->get();

        $csv = "ID,Utilisateur,Email,Type,Montant,Devise,Transaction KKiaPay,Date\n";

        foreach ($paiements as $p) {
            $csv .= implode(',', [
                $p->id,
                '"' . ($p->user->name ?? '') . '"',
                '"' . ($p->user->email ?? '') . '"',
                $p->type,
                $p->montant,
                $p->devise,
                $p->kkiapay_transaction_id ?? '',
                $p->paye_le?->format('d/m/Y H:i') ?? '',
            ]) . "\n";
        }

        return $csv;
    }

    public function exportUsersCSV(): string
    {
        $users = User::with('roles')->get();

        $csv = "ID,Nom,Email,Telephone,Role,Verifie,Abonne,Inscrit le\n";

        foreach ($users as $u) {
            $csv .= implode(',', [
                $u->id,
                '"' . $u->name . '"',
                '"' . $u->email . '"',
                '"' . ($u->phone ?? '') . '"',
                '"' . $u->getRoleNames()->implode('|') . '"',
                $u->is_verified ? 'oui' : 'non',
                $u->is_suscribed ? 'oui' : 'non',
                $u->created_at->format('d/m/Y'),
            ]) . "\n";
        }

        return $csv;
    }
}
