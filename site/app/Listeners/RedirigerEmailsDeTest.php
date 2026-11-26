<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Sur le site de test, envoie tous les e-mails à une seule adresse (MAIL_REDIRECT_TO)
 * et indique les destinataires d'origine dans l'objet.
 */
class RedirigerEmailsDeTest
{
    public function handle(MessageSending $event): void
    {
        $adresseDeTest = config('mail.redirect_to');

        if (blank($adresseDeTest) || app()->isProduction()) {
            return;
        }

        $message = $event->message;

        $destinataires = array_map(
            fn (Address $adresse) => $adresse->getAddress(),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        );

        $message->to($adresseDeTest);
        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');
        $message->subject('[TEST → '.implode(', ', $destinataires).'] '.$message->getSubject());
    }
}
