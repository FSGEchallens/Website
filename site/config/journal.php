<?php

/*
 * Journal des modifications : qui a modifié quoi, avec les anciennes valeurs et
 * les fichiers remplacés (photos, logos), pour pouvoir restaurer.
 */

return [

    // Durée de conservation des entrées et des fichiers archivés (en jours).
    'conservation_jours' => (int) env('JOURNAL_CONSERVATION_JOURS', 365),

    // Champs jamais inscrits au journal (techniques).
    'champs_ignores' => [
        'created_at', 'updated_at', 'remember_token', 'email_verified_at',
        'code_connexion', 'code_connexion_expire_le', 'code_connexion_tentatives', 'derniere_connexion_le',
    ],

    // Champs dont on note seulement le changement, sans conserver la valeur.
    'champs_sans_valeur' => ['password'],

    // Champs conservés (pour la restauration) mais affichés masqués dans le journal.
    'champs_masques' => ['avs'],

    // Dossier des fichiers archivés (disque « local », hors du dossier public).
    'dossier_archives' => 'archives-journal',

];
