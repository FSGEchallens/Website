<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Vérifie les réglages d'envoi d'e-mails (SMTP).
 * Sur Plesk : tâche planifiée « Exécuter un script PHP », script site/artisan, arguments « mail:test adresse@exemple.ch ».
 */
#[Signature('mail:test {adresse : Adresse qui doit recevoir l\'e-mail de test}')]
#[Description('Envoie un e-mail de test pour vérifier la configuration SMTP')]
class EnvoyerEmailTest extends Command
{
    public function handle(): int
    {
        $adresse = (string) $this->argument('adresse');

        if (Validator::make(['adresse' => $adresse], ['adresse' => 'email'])->fails()) {
            $this->error("« {$adresse} » n'est pas une adresse e-mail valide.");

            return self::FAILURE;
        }

        try {
            Mail::raw(
                "Bonjour,\n\nCet e-mail confirme que le site ".config('app.url')." peut envoyer des e-mails.\n\nEnvoyé le ".now()->format('d.m.Y à H:i').'.',
                fn (Message $message) => $message->to($adresse)->subject('E-mail de test – '.config('app.name')),
            );
        } catch (Throwable $exception) {
            $this->error('Échec de l\'envoi : '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("E-mail de test envoyé à {$adresse} (serveur : ".config('mail.mailers.smtp.host').').');

        return self::SUCCESS;
    }
}
