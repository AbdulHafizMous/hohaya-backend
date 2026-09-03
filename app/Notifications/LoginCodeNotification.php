<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 15;

    public function __construct(private string $code, private bool $isTwoFactor = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->isTwoFactor
            ? 'Votre code de double authentification - Hohaya'
            : 'Votre code de connexion - Hohaya';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Bonjour ' . $notifiable->name . ' 👋')
            ->line($this->isTwoFactor
                ? 'Voici votre code de vérification pour finaliser la connexion à votre compte.'
                : 'Voici votre code pour vous connecter à votre compte Hohaya.')
            ->line('Code : ' . $this->code)
            ->line('Ce code expire dans 10 minutes.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.')
            ->salutation('L\'équipe Hohaya');
    }
}
