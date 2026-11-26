<?php

namespace Tests\Feature;

use App\Support\NumeroMobile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NumeroMobileTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function numeros(): array
    {
        return [
            'format suisse habituel' => ['079 123 45 67', '+41 79 123 45 67'],
            'sans espaces' => ['0791234567', '+41 79 123 45 67'],
            'avec +41' => ['+41791234567', '+41 79 123 45 67'],
            'avec 0041' => ['0041 76 555 44 33', '+41 76 555 44 33'],
            'avec (0)' => ['+41 (0)78 111 22 33', '+41 78 111 22 33'],
            'avec points et tirets' => ['077.888-99-00', '+41 77 888 99 00'],
            'nouveau préfixe 074' => ['074 123 45 67', '+41 74 123 45 67'],
            'déjà au bon format' => ['+41 79 123 45 67', '+41 79 123 45 67'],
            'numéro fixe suisse refusé' => ['021 881 25 62', null],
            'trop court' => ['079 123 45', null],
            'vide' => ['', null],
        ];
    }

    #[DataProvider('numeros')]
    public function test_normalisation_suisse(?string $saisie, ?string $attendu): void
    {
        $this->assertSame($attendu, NumeroMobile::normaliser($saisie));
    }

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function numerosEtrangers(): array
    {
        return [
            'France, saisie nationale' => ['FR', '06 12 34 56 78', '+33 6 12 34 56 78'],
            'France, saisie internationale (pays Suisse laissé)' => ['CH', '+33 6 12 34 56 78', '+33 6 12 34 56 78'],
            'Allemagne' => ['DE', '0151 23456789', '+49 151 234 567 89'],
            'Italie : le 0 est gardé' => ['IT', '0331 234567', '+39 033 123 456 7'],
            'Portugal avec 00' => ['CH', '00351 912 345 678', '+351 912 345 678'],
            'Suisse saisie avec +41 alors que la France est choisie' => ['FR', '+41 79 123 45 67', '+41 79 123 45 67'],
            'trop court' => ['FR', '06 12', null],
            'indicatif inconnu' => ['CH', '+999 123 456 789', null],
        ];
    }

    #[DataProvider('numerosEtrangers')]
    public function test_normalisation_etrangere(string $pays, string $saisie, ?string $attendu): void
    {
        $this->assertSame($attendu, NumeroMobile::normaliser($saisie, $pays));
    }

    public function test_separer_pour_le_formulaire(): void
    {
        $this->assertSame(['pays' => 'CH', 'numero' => '79 123 45 67'], NumeroMobile::separer('+41 79 123 45 67'));
        $this->assertSame(['pays' => 'FR', 'numero' => '6 12 34 56 78'], NumeroMobile::separer('+33 6 12 34 56 78'));
        $this->assertSame(['pays' => 'US', 'numero' => '202 555 012 3'], NumeroMobile::separer('+1 202 555 012 3'));
        $this->assertSame(['pays' => 'CH', 'numero' => 'ancien format'], NumeroMobile::separer('ancien format'));
    }
}
