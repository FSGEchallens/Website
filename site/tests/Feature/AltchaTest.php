<?php

namespace Tests\Feature;

use App\Services\Altcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AltchaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fait le travail du navigateur : retrouve le nombre secret et construit la réponse du widget.
     *
     * @param  array<string, mixed>  $modifications
     */
    public static function resoudre(array $modifications = []): string
    {
        $defi = app(Altcha::class)->creerDefi();

        for ($nombre = 0; $nombre <= $defi['maxnumber']; $nombre++) {
            if (hash('sha256', $defi['salt'].$nombre) === $defi['challenge']) {
                return base64_encode((string) json_encode([
                    'algorithm' => $defi['algorithm'],
                    'challenge' => $defi['challenge'],
                    'number' => $nombre,
                    'salt' => $defi['salt'],
                    'signature' => $defi['signature'],
                    'took' => 10,
                    ...$modifications,
                ]));
            }
        }

        self::fail('Défi impossible à résoudre.');
    }

    public function test_le_defi_est_servi_au_format_attendu_par_le_widget(): void
    {
        $this->getJson(route('altcha.defi'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['algorithm', 'challenge', 'maxnumber', 'salt', 'signature'])
            ->assertJson(['algorithm' => 'SHA-256', 'maxnumber' => Altcha::NOMBRE_MAX]);
    }

    public function test_une_solution_correcte_est_acceptee_une_seule_fois(): void
    {
        $altcha = app(Altcha::class);
        $solution = self::resoudre();

        $this->assertTrue($altcha->verifier($solution));
        $this->assertTrue($altcha->verifier($solution, consommer: true));
        $this->assertFalse($altcha->verifier($solution), 'Une solution déjà utilisée est refusée.');
    }

    public function test_une_solution_falsifiee_est_refusee(): void
    {
        $altcha = app(Altcha::class);

        $this->assertFalse($altcha->verifier(self::resoudre(['number' => 999999999])));
        $this->assertFalse($altcha->verifier(self::resoudre(['signature' => str_repeat('0', 64)])));
        $this->assertFalse($altcha->verifier(self::resoudre(['algorithm' => 'SHA-1'])));
        $this->assertFalse($altcha->verifier(base64_encode('{"challenge":null,"solution":null,"test":true}')), 'Le mode « test » du widget ne passe pas.');
        $this->assertFalse($altcha->verifier('pas du base64'));
        $this->assertFalse($altcha->verifier(''));
    }

    public function test_une_solution_expiree_est_refusee(): void
    {
        $solution = self::resoudre();

        $this->travel(Altcha::VALIDITE_SECONDES + 5)->seconds();

        $this->assertFalse(app(Altcha::class)->verifier($solution));
    }

    public function test_le_composant_est_heberge_sur_le_site(): void
    {
        $this->assertFileExists(public_path('vendor/altcha/altcha.min.js'));
        $this->assertFileExists(public_path('vendor/altcha/LICENSE.txt'));

        $this->get(route('contact'))
            ->assertSee('vendor/altcha/altcha.min.js', false)
            ->assertSee('<altcha-widget', false)
            ->assertDontSee('cdn.jsdelivr', false);
    }
}
