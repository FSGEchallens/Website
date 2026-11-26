<?php

namespace Tests\Unit;

use App\Support\NumeroMobile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
            'numéro fixe refusé' => ['021 881 25 62', null],
            'numéro étranger refusé' => ['+33 6 12 34 56 78', null],
            'trop court' => ['079 123 45', null],
            'vide' => ['', null],
        ];
    }

    #[DataProvider('numeros')]
    public function test_normalisation(?string $saisie, ?string $attendu): void
    {
        $this->assertSame($attendu, NumeroMobile::normaliser($saisie));
    }
}
