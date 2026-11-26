<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lien pour choisir un mot de passe : « mot de passe oublié » (1 h) ou invitation d'un nouveau membre (7 jours).
 * Le lien ouvre une page avec un formulaire : l'ouvrir (ex. scanner d'e-mails) ne consomme pas le jeton.
 */
class LienMotDePasse extends Notification
{
    public function __construct(
        public string $token,
        public bool $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route($this->invitation ? 'bienvenue' : 'password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $message = (new MailMessage)->greeting('Bonjour '.$notifiable->prenom.',');

        if ($this->invitation) {
            return $message
                ->subject('Votre compte sur le site de la '.config('app.name'))
                ->line('Un compte a été créé pour vous sur le site de la '.config('app.name').'.')
                ->line('Votre identifiant de connexion est : **'.$notifiable->email.'**')
                ->action('Choisir mon mot de passe', $url)
                ->line('Ce lien est valable 7 jours. Passé ce délai, utilisez « Mot de passe oublié » sur la page de connexion.');
        }

        return $message
            ->subject('Réinitialisation de votre mot de passe')
            ->line('Vous avez demandé à changer votre mot de passe.')
            ->action('Choisir un nouveau mot de passe', $url)
            ->line('Ce lien est valable 60 minutes.')
            ->line('Si vous n\'avez rien demandé, ignorez simplement cet e-mail : votre mot de passe actuel reste valable.');
    }
}
