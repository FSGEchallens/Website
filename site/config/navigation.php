<?php

/*
 * Menus du site.
 *
 * - « site » : menu du haut (navigation publique). Les liens pointent encore vers l'ancien
 *   site WordPress, en attendant que les pages publiques soient recréées.
 * - « espace_membre » : menu de gauche une fois connecté. Chaque lien indique le niveau
 *   d'autorisation minimal (membre, moniteur, comite, administrateur).
 */

$ancienSite = 'https://gymechallens.ch';

return [

    'site' => [
        ['libelle' => 'Accueil', 'route' => 'accueil'],
        ['libelle' => 'La société', 'sous_menu' => [
            ['libelle' => 'Historique', 'route' => 'societe.historique'],
            ['libelle' => 'Le comité', 'route' => 'societe.comite'],
            ['libelle' => 'Statuts', 'route' => 'societe.statuts'],
            ['libelle' => 'Résultats', 'route' => 'resultats.index'],
        ]],
        ['libelle' => 'Les groupes', 'large' => true, 'sous_menu' => [
            ['libelle' => 'Baby gym', 'url' => $ancienSite.'/groupes/baby-gym/'],
            ['libelle' => 'Parent-enfant', 'url' => $ancienSite.'/groupes/parents-enfants/'],
            ['libelle' => 'Enfantine (1-2 Harmos)', 'url' => $ancienSite.'/groupes/enfantine/'],
            ['libelle' => 'Agrès préparatoire (dès 3ème Harmos)', 'url' => $ancienSite.'/groupes/agres-preparatoire-des-3-eme-harmos/'],
            ['libelle' => 'Agrès kids plaisir', 'url' => $ancienSite.'/groupes/agres-kids-plaisir/'],
            ['libelle' => 'Agrès kids concours', 'url' => $ancienSite.'/groupes/agres-kids-concours/'],
            ['libelle' => 'Polysport kids (dès 3ème Harmos)', 'url' => $ancienSite.'/groupes/polysport-kids/'],
            ['libelle' => 'Athlétisme kids 6-10 ans', 'url' => $ancienSite.'/groupes/athletisme-kids-6-10/'],
            ['libelle' => 'Athlétisme kids 10-14 ans', 'url' => $ancienSite.'/groupes/athletisme-kids-10-14/'],
            ['libelle' => 'Gym-danse plaisir petites (6-7 ans)', 'url' => $ancienSite.'/groupes/gym-danse-plaisir-petites/'],
            ['libelle' => 'Gym-danse plaisir petites (Team)', 'url' => $ancienSite.'/13615-2/'],
            ['libelle' => 'Gym-danse plaisir moyennes (8-12 ans)', 'url' => $ancienSite.'/groupes/gym-danse-plaisir-moyennes/'],
            ['libelle' => 'Gym-danse concours petites (7-12 ans)', 'url' => $ancienSite.'/groupes/gym-danse-concours-petites/'],
            ['libelle' => 'Gym-danse concours moyennes (12-16 ans)', 'url' => $ancienSite.'/groupes/gym-danse-concours-moyennes/'],
            ['libelle' => 'Gym-danse active', 'url' => $ancienSite.'/groupes/gym-danse-active/'],
            ['libelle' => 'Agrès actifs plaisir (dès 16 ans)', 'url' => $ancienSite.'/groupes/agres-plaisir/'],
            ['libelle' => 'Mise en forme', 'url' => $ancienSite.'/groupes/mise-en-forme/'],
            ['libelle' => 'Mardi Power', 'url' => $ancienSite.'/groupes/mardi-power/'],
            ['libelle' => 'Zumba', 'url' => $ancienSite.'/groupes/zumba/'],
            ['libelle' => 'Volleyball', 'url' => $ancienSite.'/groupes/volleyball/'],
            ['libelle' => 'Tarifs', 'url' => $ancienSite.'/groupes/tarifs/', 'titre' => true],
        ]],
        ['libelle' => 'Galerie', 'route' => 'galerie.index'],
        ['libelle' => 'Inscription', 'url' => $ancienSite.'/inscription/'],
        ['libelle' => 'Contact', 'route' => 'contact'],
    ],

    'espace_membre' => [
        ['titre' => 'Mon compte', 'liens' => [
            ['libelle' => 'Mon profil', 'route' => 'profil', 'actif' => 'profil', 'role' => 'membre'],
        ]],
        ['titre' => 'Gestion', 'liens' => [
            ['libelle' => 'Membres', 'route' => 'membres.index', 'actif' => 'membres.*', 'role' => 'comite'],
        ]],
        ['titre' => 'Concours et photos', 'liens' => [
            ['libelle' => 'Résultats', 'route' => 'resultats.gestion', 'actif' => 'resultats.gestion', 'role' => 'moniteur'],
            ['libelle' => 'Galerie', 'route' => 'galerie.index', 'actif' => 'galerie.*', 'role' => 'moniteur'],
        ]],
        ['titre' => 'Site public', 'liens' => [
            ['libelle' => 'Annonces', 'route' => 'annonces.index', 'actif' => 'annonces.*', 'role' => 'comite'],
            ['libelle' => 'Partenaires', 'route' => 'partenaires.index', 'actif' => 'partenaires.*', 'role' => 'comite'],
        ]],
        ['titre' => 'Administration', 'liens' => [
            ['libelle' => 'Journal des modifications', 'route' => 'journal.index', 'actif' => 'journal.*', 'role' => 'administrateur'],
        ]],
    ],

];
