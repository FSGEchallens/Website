<?php

namespace App\Rules;

use App\Support\Indicatifs;
use App\Support\NumeroMobile;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de mobile valable pour le pays choisi dans la liste (champ « mobile_pays », Suisse par défaut).
 * Suisse : mobiles 074 à 079 uniquement.
 */
class NumeroMobileValide implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $donnees = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->donnees = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pays = (string) ($this->donnees['mobile_pays'] ?? Indicatifs::PAYS_PAR_DEFAUT);

        if (NumeroMobile::normaliser((string) $value, $pays) !== null) {
            return;
        }

        $fail($pays === Indicatifs::PAYS_PAR_DEFAUT && ! str_starts_with(trim((string) $value), '+')
            ? 'Le numéro doit être un mobile suisse, par exemple '.NumeroMobile::EXEMPLE.'. Pour un numéro étranger, choisissez le pays dans la liste.'
            : 'Ce numéro de mobile n’est pas valable. Vérifiez le pays choisi et le nombre de chiffres.');
    }
}
