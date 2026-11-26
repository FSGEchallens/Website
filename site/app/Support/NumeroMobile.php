<?php

namespace App\Support;

/**
 * Numéros de mobile suisses, toujours enregistrés au format « +41 79 123 45 67 ».
 * Accepte « 079 123 45 67 », « 0791234567 », « 0041 79 … », « +41 (0)79 … ».
 */
class NumeroMobile
{
    public const EXEMPLE = '+41 79 123 45 67';

    /**
     * Renvoie le numéro au format +41, ou null s'il ne s'agit pas d'un mobile suisse valable.
     */
    public static function normaliser(?string $saisie): ?string
    {
        $saisie = trim((string) $saisie);

        if ($saisie === '') {
            return null;
        }

        // « +41 (0)79 … » : le (0) est à ignorer.
        $saisie = preg_replace('/\(\s*0\s*\)/', '', $saisie) ?? $saisie;
        $chiffres = preg_replace('/\D/', '', $saisie) ?? '';

        $national = match (true) {
            str_starts_with($chiffres, '0041') => substr($chiffres, 4),
            str_starts_with($chiffres, '41') && strlen($chiffres) === 11 => substr($chiffres, 2),
            str_starts_with($chiffres, '0') => substr($chiffres, 1),
            default => $chiffres,
        };

        // Mobile suisse : 9 chiffres commençant par 74 à 79.
        if (preg_match('/^7[4-9]\d{7}$/', $national) !== 1) {
            return null;
        }

        return '+41 '.substr($national, 0, 2).' '.substr($national, 2, 3).' '.substr($national, 5, 2).' '.substr($national, 7, 2);
    }
}
