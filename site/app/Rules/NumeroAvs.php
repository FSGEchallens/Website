<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro AVS suisse (AVS13) : 756.XXXX.XXXX.XX avec chiffre de contrôle EAN-13.
 */
class NumeroAvs implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $chiffres = self::chiffres((string) $value);

        if (strlen($chiffres) !== 13 || ! str_starts_with($chiffres, '756') || ! self::controleValide($chiffres)) {
            $fail('Le numéro AVS n\'est pas valide (format 756.XXXX.XXXX.XX).');
        }
    }

    /**
     * Met le numéro au format officiel 756.1234.5678.97.
     */
    public static function formater(?string $valeur): ?string
    {
        $chiffres = self::chiffres((string) $valeur);

        if ($chiffres === '') {
            return null;
        }

        if (strlen($chiffres) !== 13) {
            return $valeur;
        }

        return substr($chiffres, 0, 3).'.'.substr($chiffres, 3, 4).'.'.substr($chiffres, 7, 4).'.'.substr($chiffres, 11, 2);
    }

    private static function chiffres(string $valeur): string
    {
        return preg_replace('/\D/', '', $valeur) ?? '';
    }

    private static function controleValide(string $chiffres): bool
    {
        $somme = 0;

        for ($i = 0; $i < 12; $i++) {
            $somme += (int) $chiffres[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $somme % 10) % 10 === (int) $chiffres[12];
    }
}
