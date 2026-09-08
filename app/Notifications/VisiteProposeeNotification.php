<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisiteProposeeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 15;

    public function __construct(private Property $property, private Visite $visite) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Le propriétaire vous propose une visite - ' . $this->property->title)
            ->greeting('Bonjour ' . $notifiable->name . ' 👋')
            ->line('Le propriétaire de l\'annonce « ' . $this->property->title . ' » vous propose une visite.')
            ->line('Date proposée : ' . $this->visite->date_souhaitee->format('d/m/Y à H:i'))
            ->when($this->visite->message, fn($mail) => $mail->line('Message : ' . $this->visite->message))
            ->line('Connectez-vous à votre espace pour confirmer ou refuser cette proposition.')
            ->salutation('L\'équipe Hohaya');
    }
}
