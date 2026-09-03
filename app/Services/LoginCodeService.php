<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginCodeService
{
    private const EXPIRATION_MINUTES = 10;
    private const MAX_TENTATIVES = 5;

    public function generateAndSend(User $user, string $type): void
    {
        // On invalide les anciens codes du même type pour ce user avant d'en émettre un nouveau.
        LoginCode::where('id_user', $user->id)
            ->where('type', $type)
            ->whereNull('consumed_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        LoginCode::create([
            'id_user'    => $user->id,
            'code'       => Hash::make($code),
            'type'       => $type,
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        $user->notify(new LoginCodeNotification($code, $type === '2fa'));
    }

    /**
     * Vérifie le code fourni pour un user et un type donnés.
     * Lève une ValidationException si invalide/expiré/trop de tentatives.
     */
    public function verify(User $user, string $type, string $code): void
    {
        $loginCode = LoginCode::where('id_user', $user->id)
            ->where('type', $type)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (!$loginCode) {
            throw ValidationException::withMessages([
                'code' => ['Aucun code en attente. Veuillez en redemander un.'],
            ]);
        }

        if ($loginCode->expires_at->isPast()) {
            $loginCode->delete();
            throw ValidationException::withMessages([
                'code' => ['Ce code a expiré. Veuillez en redemander un.'],
            ]);
        }

        if ($loginCode->tentatives >= self::MAX_TENTATIVES) {
            $loginCode->delete();
            throw ValidationException::withMessages([
                'code' => ['Trop de tentatives. Veuillez redemander un nouveau code.'],
            ]);
        }

        if (!Hash::check($code, $loginCode->code)) {
            $loginCode->increment('tentatives');
            throw ValidationException::withMessages([
                'code' => ['Code incorrect.'],
            ]);
        }

        $loginCode->update(['consumed_at' => now()]);
    }
}
