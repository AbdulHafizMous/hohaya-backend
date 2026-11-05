<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompteCreéAutomatiquementNotification extends Notification
{
    public function __construct(
        private readonly string $plainPassword,
        private readonly string $frontendUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte Hohaya a été créé')
            ->greeting('Bonjour ' . $notifiable->name . ' 👋')
            ->line('Un compte Hohaya a été créé automatiquement pour vous lors de votre démarche.')
            ->line('Voici vos identifiants de connexion :')
            ->line('**Email :** ' . ($notifiable->email ?? $notifiable->phone))
            ->line('**Mot de passe temporaire :** `' . $this->plainPassword . '`')
            ->action('Me connecter à Hohaya', $this->frontendUrl . '/login')
            ->line('Pensez à changer votre mot de passe après votre première connexion.')
            ->line('Si vous n\'êtes pas à l\'origine de cette démarche, ignorez cet email.')
            ->salutation('L\'équipe Hohaya');
    }
}
