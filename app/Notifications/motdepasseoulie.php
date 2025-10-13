<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public function __construct(public $token) {}

    public function via($notifiable) {
        return ['mail'];
    }

    public function toMail($notifiable) {
        $url = config('app.frontend_url') . "/reset-password?email={$notifiable->email}&token={$this->token}";

        return (new MailMessage)
            ->subject('Réinitialisation de mot de passe')
            ->line('Bonjour,')
            ->line('Vous avez demandé à réinitialiser votre mot de passe.')
            ->action('Réinitialiser le mot de passe', $url)
            ->line('Si vous n’avez pas fait cette demande, ignorez cet email.');
    }
}