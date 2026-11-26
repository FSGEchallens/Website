<?php

namespace App\Support;

/**
 * Numéros de mobile, toujours enregistrés au format international :
 * - Suisse (par défaut) : uniquement les mobiles 074 à 079, au format « +41 79 123 45 67 » ;
 * - autres pays : « +33 6 12 34 56 78 », « +49 151 234 567 89 »… (6 à 12 chiffres).
 * Accepte « 079 123 45 67 », « 0791234567 », « 0041 79 … », « +41 (0)79 … ».
 */
class NumeroMobile
{
    public const EXEMPLE = '+41 79 123 45 67';

    /**
     * Renvoie le numéro au format international, ou null s'il n'est pas valable.
     *
     * @param  string  $pays  Pays choisi dans la liste (code ISO), utilisé si le numéro est saisi sans indicatif.
     */
    public static function normaliser(?string $saisie, string $pays = Indicatifs::PAYS_PAR_DEFAUT): ?string
    {
        $saisie = trim((string) $saisie);

        if ($saisie === '') {
            return null;
        }

        // « +41 (0)79 … » : le (0) est à ignorer.
        $saisie = preg_replace('/\(\s*0\s*\)/', '', $saisie) ?? $saisie;
        $chiffres = preg_replace('/\D/', '', $saisie) ?? '';
        $international = str_starts_with($saisie, '+') || str_starts_with($chiffres, '00');

        if ($international) {
            $chiffres = str_starts_with($chiffres, '00') ? substr($chiffres, 2) : $chiffres;
            $indicatif = Indicatifs::indicatifEnTete($chiffres);

            if ($indicatif === null) {
                return null;
            }

            $national = substr($chiffres, strlen($indicatif));
        } else {
            $indicatif = Indicatifs::indicatif($pays) ?? '41';

            // Suisse : « 41791234567 » saisi sans « + ».
            if ($indicatif === '41' && str_starts_with($chiffres, '41') && strlen($chiffres) === 11) {
                $chiffres = substr($chiffres, 2);
            }

            $national = $chiffres;
        }

        return $indicatif === '41'
            ? self::suisse($national)
            : self::etranger($indicatif, $national);
    }

    /**
     * Sépare un numéro enregistré en [pays, numéro sans indicatif] pour le formulaire.
     *
     * @return array{pays: string, numero: string}
     */
    public static function separer(?string $mobile): array
    {
        $mobile = trim((string) $mobile);

        if (preg_match('/^\+(\d{1,3})\s+(.+)$/', $mobile, $m) === 1) {
            $pays = Indicatifs::paysDeLIndicatif($m[1]);

            if ($pays !== null) {
                return ['pays' => $pays, 'numero' => $m[2]];
            }
        }

        return ['pays' => Indicatifs::PAYS_PAR_DEFAUT, 'numero' => $mobile];
    }

    private static function suisse(string $national): ?string
    {
        $national = ltrim($national, '0');

        // Mobile suisse : 9 chiffres commençant par 74 à 79.
        if (preg_match('/^7[4-9]\d{7}$/', $national) !== 1) {
            return null;
        }

        return '+41 '.substr($national, 0, 2).' '.substr($national, 2, 3).' '.substr($national, 5, 2).' '.substr($national, 7, 2);
    }

    private static function etranger(string $indicatif, string $national): ?string
    {
        // Le « 0 » national est supprimé, sauf en Italie (et au Vatican) où il fait partie du numéro.
        if ($indicatif !== '39') {
            $national = ltrim($national, '0');
        }

        if (preg_match('/^\d{6,12}$/', $national) !== 1) {
            return null;
        }

        // France : 6 12 34 56 78 ; ailleurs : groupes de 3 chiffres.
        $groupes = $indicatif === '33' && strlen($national) === 9
            ? [substr($national, 0, 1), ...str_split(substr($national, 1), 2)]
            : str_split($national, 3);

        return '+'.$indicatif.' '.implode(' ', $groupes);
    }
}
