<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message envoyé depuis le formulaire de contact du site.
 * « Répondre » dans la messagerie répond directement à l'expéditeur.
 */
class MessageContact extends Mailable
{
    /**
     * @param  array{prenom: string, nom: string, email: string, sujet: ?string, message: string}  $donnees
     */
    public function __construct(public array $donnees) {}

    public function envelope(): Envelope
    {
        $sujet = $this->donnees['sujet'] ?: 'Message depuis le site';

        return new Envelope(
            replyTo: [new Address($this->donnees['email'], $this->donnees['prenom'].' '.$this->donnees['nom'])],
            subject: '[Site] '.$sujet,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.message-contact');
    }
}
