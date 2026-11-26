<?php

namespace App\Validation;

/**
 * Règles de saisie communes à la fiche membre (comité) et au profil (membre lui-même).
 */
class ReglesMembre
{
    /**
     * Coordonnées privées, modifiables par le membre lui-même.
     *
     * @return array<string, array<int, string>>
     */
    public static function coordonnees(): array
    {
        $telephone = ['string', 'max:20', 'regex:/^[0-9+ ().\/-]{6,20}$/'];

        return [
            'rue' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:10'],
            'npa' => ['nullable', 'string', 'regex:/^[0-9]{4,5}$/'],
            'localite' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', ...$telephone],
            'mobile' => ['required', ...$telephone],
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
            'afficher_photo' => ['boolean'],
            'afficher_adresse' => ['boolean'],
            'afficher_telephone' => ['boolean'],
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
            'telephone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'mobile.regex' => 'Le numéro de mobile n\'est pas valide.',
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
        ];
    }

    /**
     * Remplace les textes vides par NULL et retire les espaces superflus.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    public static function nettoyer(array $donnees): array
    {
        return array_map(
            fn ($valeur) => is_string($valeur) ? (trim($valeur) === '' ? null : trim($valeur)) : $valeur,
            $donnees,
        );
    }
}
