<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Liste des pays et de leurs indicatifs (config/indicatifs.php) : Suisse en premier,
 * puis ordre alphabétique (sans tenir compte des accents), avec le drapeau en emoji.
 */
class Indicatifs
{
    public const PAYS_PAR_DEFAUT = 'CH';

    /**
     * @return array<string, array{nom: string, indicatif: string, drapeau: string}>
     */
    public static function liste(): array
    {
        $pays = [];

        foreach (config('indicatifs.pays') as $iso => [$nom, $indicatif]) {
            $pays[$iso] = ['nom' => $nom, 'indicatif' => $indicatif, 'drapeau' => self::drapeau($iso)];
        }

        uksort($pays, function (string $a, string $b) use ($pays): int {
            if ($a === self::PAYS_PAR_DEFAUT || $b === self::PAYS_PAR_DEFAUT) {
                return $a === self::PAYS_PAR_DEFAUT ? -1 : 1;
            }

            return strcmp(Str::lower(Str::ascii($pays[$a]['nom'])), Str::lower(Str::ascii($pays[$b]['nom'])));
        });

        return $pays;
    }

    public static function indicatif(string $iso): ?string
    {
        return config('indicatifs.pays.'.strtoupper($iso))[1] ?? null;
    }

    /**
     * Pays correspondant à un indicatif (« 41 » → « CH », « 1 » → « US »…).
     */
    public static function paysDeLIndicatif(string $indicatif): ?string
    {
        if ($indicatif === '41') {
            return self::PAYS_PAR_DEFAUT;
        }

        $principal = config('indicatifs.principal.'.$indicatif);

        if ($principal !== null) {
            return $principal;
        }

        foreach (config('indicatifs.pays') as $iso => [, $code]) {
            if ($code === $indicatif) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * Trouve l'indicatif au début d'une suite de chiffres internationale (« 33612345678 » → « 33 »).
     */
    public static function indicatifEnTete(string $chiffres): ?string
    {
        foreach ([3, 2, 1] as $longueur) {
            $candidat = substr($chiffres, 0, $longueur);

            if (self::paysDeLIndicatif($candidat) !== null) {
                return $candidat;
            }
        }

        return null;
    }

    /**
     * Drapeau en emoji à partir du code ISO (« CH » → 🇨🇭).
     */
    public static function drapeau(string $iso): string
    {
        $iso = strtoupper($iso);

        if (preg_match('/^[A-Z]{2}$/', $iso) !== 1) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($iso[0]) - 65).mb_chr(0x1F1E6 + ord($iso[1]) - 65);
    }
}
