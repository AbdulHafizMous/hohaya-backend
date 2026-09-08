<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private LoginCodeService $loginCodeService,
        private AbonnementService $abonnementService,
    ) {}

    public function register(array $data): array
    {
        // Vérifier si le compte existe mais est soft-deleted
        $deleted = User::withTrashed()
            ->where(function ($q) use ($data) {
                if (!empty($data['email'])) $q->where('email', $data['email']);
                if (!empty($data['phone'])) $q->orWhere('phone', $data['phone']);
            })
            ->whereNotNull('deleted_at')
            ->first();

        if ($deleted) {
            throw ValidationException::withMessages([
                'email' => [
                    'Un compte avec ces informations existe mais a été supprimé. '
                    . 'Contactez le support à support@hohaya.com pour le restaurer.'
                ],
            ]);
        }

        $role = $data['role'];
        unset($data['role'], $data['password_confirmation']);

        // Tous les rôles définissent un mot de passe à l'inscription (chercheur inclus).
        // Le chercheur pourra toujours, en plus, se connecter par code email (voir requestLoginCode).
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $user->assignRole($role);

        // Le propriétaire démarre automatiquement sur le plan Gratuit (jusqu'à ce qu'il change
        // de plan ou que l'admin réactive l'obligation d'abonnement — voir CheckAbonnementActif).
        if ($role === 'owner') {
            $this->abonnementService->affecterAbonnementGratuit($user);
        }

        $user->notify(new VerifyEmailNotification());

        return [
            'requires_email_verification' => true,
            'email'                       => $user->email,
        ];
    }

    public function login(array $data): array
    {
        // Vérifier d'abord si le compte existe en soft-delete
        $deletedUser = User::withTrashed()
            ->where(function ($q) use ($data) {
                if (!empty($data['email'])) $q->where('email', $data['email']);
                if (!empty($data['phone'])) $q->orWhere('phone', $data['phone']);
            })
            ->whereNotNull('deleted_at')
            ->first();

        if ($deletedUser) {
            throw ValidationException::withMessages([
                'email' => [
                    'Ce compte a été supprimé. '
                    . 'Contactez le support à support@hohaya.com si vous souhaitez le restaurer.'
                ],
            ]);
        }

        $user = User::where(function ($q) use ($data) {
            if (!empty($data['email'])) $q->where('email', $data['email']);
            if (!empty($data['phone'])) $q->orWhere('phone', $data['phone']);
        })->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte trouvé avec ces informations.'],
            ]);
        }

        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        // 2FA : réservé aux owner/admin ayant activé l'option dans leurs paramètres.
        if ($user->two_factor_enabled && !$user->isSeeker()) {
            $this->loginCodeService->generateAndSend($user, '2fa');

            return [
                'two_factor_required' => true,
                'user_id'             => $user->id,
            ];
        }

        return $this->generateTokens($user);
    }

    /**
     * Connexion par code envoyé par email, réservée aux chercheurs (seeker).
     */
    public function requestLoginCode(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte trouvé avec cet email.'],
            ]);
        }

        if (!$user->isSeeker()) {
            throw ValidationException::withMessages([
                'email' => ['La connexion par code est réservée aux chercheurs.'],
            ]);
        }

        $this->loginCodeService->generateAndSend($user, 'login_seeker');
    }

    public function verifyLoginCode(string $email, string $code): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte trouvé avec cet email.'],
            ]);
        }

        $this->loginCodeService->verify($user, 'login_seeker', $code);

        return $this->generateTokens($user);
    }

    /**
     * Finalise une connexion owner/admin après vérification du mot de passe (voir login()).
     */
    public function verifyTwoFactor(int $userId, string $code): array
    {
        $user = User::find($userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'user_id' => ['Compte introuvable.'],
            ]);
        }

        $this->loginCodeService->verify($user, '2fa', $code);

        return $this->generateTokens($user);
    }

    public function refresh(string $refreshToken): array
    {
        $token = PersonalAccessToken::findToken($refreshToken);

        if (!$token || !$token->can('refresh')) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token invalide.'],
            ]);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token expiré. Veuillez vous reconnecter.'],
            ]);
        }

        $user = $token->tokenable;

        // Vérifier que le compte n'a pas été supprimé entre-temps
        if (!$user || $user->trashed()) {
            $token->delete();
            throw ValidationException::withMessages([
                'refresh_token' => ['Ce compte n\'existe plus.'],
            ]);
        }

        $token->delete();
        return $this->generateTokens($user);
    }

    public function issueTokensFor(User $user): array
    {
        return $this->generateTokens($user);
    }

    private function generateTokens(User $user): array
    {
        $user->tokens()->where('name', 'access-token')->delete();

        $accessToken  = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(7))->plainTextToken;

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => 900,
            'user'          => $this->formatUser($user),
        ];
    }

    public function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'avatar'            => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'adress'            => $user->adress,
            'preferences'       => $user->preferences,
            'is_verified'       => $user->is_verified,
            'two_factor_enabled' => $user->two_factor_enabled,
            'is_suscribed'      => $user->is_suscribed,
            'email_verified_at' => $user->email_verified_at,
            'subscription_end'  => $user->subscription_end,
            'roles'             => $user->getRoleNames(),
            'created_at'        => $user->created_at,
        ];
    }
}