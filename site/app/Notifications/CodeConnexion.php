<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Code à 6 chiffres pour la 2e étape de connexion.
 */
class CodeConnexion extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de connexion : '.$this->code)
            ->greeting('Bonjour '.$notifiable->prenom.',')
            ->line('Voici votre code pour vous connecter au site de la '.config('app.name').' :')
            ->line('# '.$this->code)
            ->line('Ce code est valable '.config('auth.code_connexion.validite_minutes').' minutes.')
            ->line('Si vous n\'essayez pas de vous connecter, quelqu\'un connaît peut-être votre mot de passe : changez-le dès que possible.');
    }
}
