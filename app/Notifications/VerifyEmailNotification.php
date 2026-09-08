<?php

namespace App\Notifications;

use App\Models\EmailVerificationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 15;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Vérifiez votre adresse email - Hohaya')
            ->greeting('Bonjour ' . $notifiable->name . ' 👋')
            ->line('Merci de vous être inscrit sur Hohaya !')
            ->line('Cliquez sur le bouton ci-dessous pour vérifier votre adresse email.')
            ->action('Vérifier mon email', $verificationUrl)
            ->line('Ce lien expire dans 60 minutes.')
            ->line('Si vous n\'avez pas créé de compte, ignorez cet email.')
            ->salutation('L\'équipe Hohaya');
    }

    protected function verificationUrl(object $notifiable): string
    {
        // Token opaque stocké en base, plutôt qu'une signature calculée sur l'URL exacte
        // (query, ordre des paramètres, encodage) : robuste face à un client mail (Gmail
        // Safe Links, etc.) ou tout intermédiaire réseau qui pourrait altérer l'URL brute.
        $token = Str::random(48);

        EmailVerificationToken::create([
            'id_user'    => $notifiable->getKey(),
            'token'      => hash('sha256', $token),
            'expires_at' => now()->addMinutes(60),
        ]);

        return rtrim(env('FRONTEND_URL'), '/') . '/verify-email/' . $token;
    }
}
