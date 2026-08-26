<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactDebloqueNotification extends Notification
{
    use Queueable;

    public function __construct(private Property $property, private User $proprietaire) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Contact débloqué - ' . $this->property->title)
            ->greeting('Bonjour ' . $notifiable->name . ' 👋')
            ->line('Vous avez débloqué le contact du propriétaire pour l\'annonce « ' . $this->property->title . ' ».')
            ->line('Voici ses coordonnées :')
            ->line('Nom : ' . $this->proprietaire->name);

        if ($this->proprietaire->phone) {
            $message->line('Téléphone : ' . $this->proprietaire->phone);
        }
        if ($this->proprietaire->email) {
            $message->line('Email : ' . $this->proprietaire->email);
        }

        return $message
            ->line('Vous pouvez le contacter directement pour organiser une visite ou finaliser la location.')
            ->salutation('L\'équipe Hohaya');
    }
}
