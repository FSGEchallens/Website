<?php

/*
 * Contenus généraux du site public.
 * Les partenaires sont dans la base de données (gérés par le comité : Gestion → Partenaires).
 */

return [

    // Adresse publique du site, affichée dans les textes (ex. e-mails du formulaire de contact).
    'adresse_publique' => 'www.gymechallens.ch',

    'facebook' => 'https://www.facebook.com/fsgechallens',

    'inscription' => 'https://gymechallens.ch/inscription/',

    // Adresse qui reçoit les messages du formulaire de contact.
    'email_contact' => env('CONTACT_EMAIL', 'contact@gymechallens.ch'),

];
