<?php

namespace App\Validation;

use App\Rules\NumeroMobileValide;
use App\Support\Indicatifs;
use App\Support\NumeroMobile;
use Illuminate\Validation\Rule;

/**
 * Règles de saisie communes à la fiche membre (comité) et au profil (membre lui-même).
 */
class ReglesMembre
{
    /**
     * Coordonnées privées, modifiables par le membre lui-même.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function coordonnees(): array
    {
        return [
            'rue' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:10'],
            'npa' => ['nullable', 'string', 'regex:/^[0-9]{4,5}$/'],
            'localite' => ['nullable', 'string', 'max:100'],
            'mobile_pays' => ['required', Rule::in(array_keys(config('indicatifs.pays')))],
            'mobile' => ['required', 'string', 'max:25', new NumeroMobileValide],
            'email_prive' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * Choix de ce qui est visible publiquement.
     *
     * @return array<string, array<int, string>>
     */
    public static function affichage(): array
    {
        return [
            'afficher_mobile' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'npa.regex' => 'Le code postal doit contenir 4 chiffres (5 pour l\'étranger).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributs(): array
    {
        return [
            'npa' => 'code postal',
            'email_prive' => 'e-mail privé',
            'mobile_pays' => 'pays du mobile',
        ];
    }

    /**
     * Remplace les textes vides par NULL, retire les espaces superflus et met le mobile
     * au format international (+41 79 123 45 67) selon le pays choisi.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    public static function nettoyer(array $donnees): array
    {
        $donnees = array_map(
            fn ($valeur) => is_string($valeur) ? (trim($valeur) === '' ? null : trim($valeur)) : $valeur,
            $donnees,
        );

        $pays = (string) ($donnees['mobile_pays'] ?? Indicatifs::PAYS_PAR_DEFAUT);
        unset($donnees['mobile_pays']);

        if (array_key_exists('mobile', $donnees) && $donnees['mobile'] !== null) {
            $donnees['mobile'] = NumeroMobile::normaliser($donnees['mobile'], $pays) ?? $donnees['mobile'];
        }

        return $donnees;
    }
}
