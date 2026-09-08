<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactDebloqueParChercheurNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 15;

    public function __construct(private Property $property, private User $chercheur) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Un chercheur a débloqué votre contact - ' . $this->property->title)
            ->greeting('Bonjour ' . $notifiable->name . ' 👋')
            ->line('Un chercheur a débloqué votre contact pour l\'annonce « ' . $this->property->title . ' ».')
            ->line('Voici ses coordonnées :')
            ->line('Nom : ' . $this->chercheur->name);

        if ($this->chercheur->phone) {
            $message->line('Téléphone : ' . $this->chercheur->phone);
        }
        if ($this->chercheur->email) {
            $message->line('Email : ' . $this->chercheur->email);
        }

        return $message
            ->line('Vous pouvez le contacter directement pour organiser une visite.')
            ->salutation('L\'équipe Hohaya');
    }
}
