<?php

namespace Tests\Unit;

use App\Rules\NumeroAvs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumeroAvsTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function numeros(): array
    {
        return [
            'valide avec points' => ['756.1234.5678.97', true],
            'valide sans points' => ['7561234567897', true],
            'mauvais chiffre de contrôle' => ['756.1234.5678.98', false],
            'ne commence pas par 756' => ['757.1234.5678.97', false],
            'trop court' => ['756.1234.5678', false],
        ];
    }

    #[DataProvider('numeros')]
    public function test_validation_du_numero_avs(string $numero, bool $attendu): void
    {
        $echec = false;

        (new NumeroAvs)->validate('avs', $numero, function () use (&$echec): void {
            $echec = true;
        });

        $this->assertSame($attendu, ! $echec);
    }

    public function test_formate_le_numero_au_format_officiel(): void
    {
        $this->assertSame('756.1234.5678.97', NumeroAvs::formater('7561234567897'));
        $this->assertNull(NumeroAvs::formater(''));
    }
}
