<?php

namespace App\Rules;

use App\Support\NumeroMobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de mobile suisse (074 à 079), saisi librement puis enregistré au format +41.
 */
class MobileSuisse implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (NumeroMobile::normaliser((string) $value) === null) {
            $fail('Le numéro doit être un mobile suisse, par exemple '.NumeroMobile::EXEMPLE.'.');
        }
    }
}
