<?php

namespace App\Enums;

/**
 * Autorisation d'un membre (une seule par personne). Chaque niveau a aussi les droits des niveaux inférieurs :
 * Membre < Moniteur < Comité < Administrateur.
 * Le lien moniteur ↔ cours (principal / secondaire) viendra avec la gestion des cours.
 */
enum Role: string
{
    case Membre = 'membre';
    case Moniteur = 'moniteur';
    case Comite = 'comite';
    case Administrateur = 'administrateur';

    public function niveau(): int
    {
        return match ($this) {
            self::Membre => 1,
            self::Moniteur => 2,
            self::Comite => 3,
            self::Administrateur => 4,
        };
    }

    public function auMoins(self $role): bool
    {
        return $this->niveau() >= $role->niveau();
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Membre => 'Membre',
            self::Moniteur => 'Moniteur',
            self::Comite => 'Comité',
            self::Administrateur => 'Administrateur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Membre => 'Voit et modifie son propre profil.',
            self::Moniteur => 'Gère les inscriptions de ses cours.',
            self::Comite => 'Gère les membres, les cours et les inscriptions.',
            self::Administrateur => 'Webmaster : tous les droits.',
        };
    }
}
