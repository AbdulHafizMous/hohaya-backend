<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

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
        // URL signée générée côté API (pour la validation), puis réécrite pour pointer
        // vers la page Next.js qui l'appelle en JS — l'utilisateur ne doit jamais voir l'API brute.
        $apiUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $query = parse_url($apiUrl, PHP_URL_QUERY);

        return rtrim(env('FRONTEND_URL'), '/')
            . '/verify-email/' . $notifiable->getKey() . '/' . sha1($notifiable->getEmailForVerification())
            . ($query ? '?' . $query : '');
    }
}