<?php

namespace Tests\Feature;

use App\Models\Resultat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Import depuis l'ancien site, avec une fausse page (données FICTIVES).
 */
class ImportResultatsTest extends TestCase
{
    use RefreshDatabase;

    private function imageJpeg(): string
    {
        $image = imagecreatetruecolor(1200, 800);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    private function fausseAnciennePage(): string
    {
        return <<<'HTML'
            <html><body><header><h3>Menu</h3></header>
            <article><div class="entry-content">
                <h3><a href="https://ancien.exemple/1/">02 avril 2022, Master 1 centre à Exempleville</a></h3>
                <ul><li>C3 fille, Fictive Alice, 3e place avec 36.20</li></ul>
                <p><img src="https://ancien.exemple/wp-content/uploads/photo-1.jpg" alt=""><img src="/wp-content/uploads/photo-2.jpg" alt=""></p>
                <h3><a href="https://ancien.exemple/2/">14-15 septembre 2019, Championnat vaudois à Nulle-Part</a></h3>
                <p>Toutes nos félicitations aux <strong>gymnastes</strong> !</p>
                <h3>13-23 juin 2019, Fête fédérale à Aarau.</h3>
                <p>24e rang sur 52.</p>
                <h3>Un jour, sans date reconnue</h3>
            </div></article></body></html>
            HTML;
    }

    public function test_import_des_resultats_et_des_photos(): void
    {
        Storage::fake('local');
        Http::fake([
            'ancien.exemple/resultat/' => Http::response($this->fausseAnciennePage()),
            'ancien.exemple/wp-content/*' => Http::response($this->imageJpeg()),
        ]);

        $this->artisan('resultats:importer', ['--url' => 'https://ancien.exemple/resultat/'])
            ->expectsOutputToContain('3 résultat(s) importé(s), 1 ignoré(s)')
            ->assertSuccessful();

        $this->assertSame(3, Resultat::count());

        $master = Resultat::firstWhere('titre', 'Master 1 centre à Exempleville');
        $this->assertSame('2022-04-02', $master->date->toDateString());
        $this->assertStringContainsString('Fictive Alice', $master->contenu);
        $this->assertStringNotContainsString('<img', $master->contenu);
        $this->assertCount(2, $master->photos);
        Storage::disk('local')->assertExists('resultats/photos/'.$master->photos[1]);

        $championnat = Resultat::firstWhere('titre', 'Championnat vaudois à Nulle-Part');
        $this->assertSame('2019-09-15', $championnat->date_fin->toDateString());

        $this->assertNotNull(Resultat::firstWhere('titre', 'Fête fédérale à Aarau'), 'Le point final du titre est retiré.');
    }

    public function test_relancer_l_import_ne_cree_pas_de_doublons(): void
    {
        Storage::fake('local');
        Http::fake([
            'ancien.exemple/resultat/' => Http::response($this->fausseAnciennePage()),
            'ancien.exemple/wp-content/*' => Http::response($this->imageJpeg()),
        ]);

        $this->artisan('resultats:importer', ['--url' => 'https://ancien.exemple/resultat/'])->assertSuccessful();
        $this->artisan('resultats:importer', ['--url' => 'https://ancien.exemple/resultat/'])
            ->expectsOutputToContain('0 résultat(s) importé(s)')
            ->assertSuccessful();

        $this->assertSame(3, Resultat::count());
    }

    public function test_page_inaccessible(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('resultats:importer', ['--url' => 'https://ancien.exemple/resultat/'])->assertFailed();
    }
}
