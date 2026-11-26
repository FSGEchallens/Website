<?php

namespace App\Enums;

enum Civilite: string
{
    case Madame = 'madame';
    case Monsieur = 'monsieur';

    public function libelle(): string
    {
        return match ($this) {
            self::Madame => 'Madame',
            self::Monsieur => 'Monsieur',
        };
    }
}
