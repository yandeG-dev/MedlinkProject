<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct($token, $user)
    {
        $this->token = $token;
        $this->user = $user;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Pour une API, on envoie juste le token dans l'email
        // L'application frontend gérera l'interface de réinitialisation
        return $this->subject('Réinitialisation de votre mot de passe - Medlink')
                    ->text('emails.password-reset-plain') // Version texte simple
                    ->with([
                        'token' => $this->token,
                        'user' => $this->user
                    ]);
    }
}