<?php

namespace App\Enums;

/**
 * Autorisations d'un membre. Une personne peut en avoir plusieurs.
 * Le lien moniteur ↔ cours (principal / secondaire) viendra avec la gestion des cours.
 */
enum Role: string
{
    case Administrateur = 'administrateur';
    case Comite = 'comite';
    case Moniteur = 'moniteur';

    public function libelle(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::Comite => 'Comité',
            self::Moniteur => 'Moniteur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Administrateur => 'Webmaster : tous les droits.',
            self::Comite => 'Gère les membres, les cours et les inscriptions.',
            self::Moniteur => 'Gère les inscriptions de ses cours.',
        };
    }
}
