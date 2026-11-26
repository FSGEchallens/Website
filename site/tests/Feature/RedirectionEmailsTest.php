<?php

namespace Tests\Feature;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class RedirectionEmailsTest extends TestCase
{
    private function envoyerEtRecuperer(): Email
    {
        config(['mail.default' => 'array']);

        Mail::raw('Bonjour', function (Message $message): void {
            $message->to('julie@example.org')->cc('parent@example.org')->subject('Bienvenue');
        });

        /** @var Email $email */
        $email = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        return $email;
    }

    /**
     * @param  array<int, Address>  $adresses
     * @return array<int, string>
     */
    private function adresses(array $adresses): array
    {
        return array_map(fn (Address $adresse) => $adresse->getAddress(), $adresses);
    }

    public function test_tous_les_e_mails_partent_a_l_adresse_de_test(): void
    {
        config(['mail.redirect_to' => 'test@example.org']);

        $email = $this->envoyerEtRecuperer();

        $this->assertSame(['test@example.org'], $this->adresses($email->getTo()));
        $this->assertSame([], $email->getCc());
        $this->assertSame('[TEST → julie@example.org, parent@example.org] Bienvenue', $email->getSubject());
    }

    public function test_sans_reglage_les_e_mails_ne_sont_pas_modifies(): void
    {
        config(['mail.redirect_to' => null]);

        $email = $this->envoyerEtRecuperer();

        $this->assertSame(['julie@example.org'], $this->adresses($email->getTo()));
        $this->assertSame('Bienvenue', $email->getSubject());
    }

    public function test_jamais_de_redirection_en_production(): void
    {
        config(['mail.redirect_to' => 'test@example.org']);
        $this->app->detectEnvironment(fn () => 'production');

        $email = $this->envoyerEtRecuperer();

        $this->assertSame(['julie@example.org'], $this->adresses($email->getTo()));
    }
}
