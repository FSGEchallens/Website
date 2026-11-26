<?php

namespace Tests\Feature;

use App\Livewire\Profil\MonProfil;
use App\Models\User;
use App\Support\Indicatifs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatifsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_suisse_en_premier_puis_ordre_alphabetique(): void
    {
        $liste = Indicatifs::liste();
        $codes = array_keys($liste);

        $this->assertSame('CH', $codes[0]);
        $this->assertSame(['nom' => 'Suisse', 'indicatif' => '41', 'drapeau' => '🇨🇭'], $liste['CH']);
        $this->assertSame(['AF', 'ZA', 'AL'], array_slice($codes, 1, 3), 'Afghanistan, Afrique du Sud, Albanie…');

        $noms = array_map(fn ($pays) => $pays['nom'], array_slice($liste, 1));
        $tries = $noms;
        usort($tries, fn ($a, $b) => strcmp(strtolower(Str::ascii($a)), strtolower(Str::ascii($b))));
        $this->assertSame($tries, array_values($noms));
    }

    public function test_le_profil_affiche_la_liste_avec_la_suisse_preselectionnee(): void
    {
        $membre = User::factory()->create(['mobile' => '+41 79 123 45 67']);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->assertSet('mobile_pays', 'CH')
            ->assertSet('mobile', '79 123 45 67')
            ->assertSee('🇨🇭 Suisse (+41)')
            ->assertSee('🇫🇷 France (+33)')
            ->assertSeeHtml('class="indicatif__affichage select-drapeaux" x-text="affichage" aria-hidden="true">🇨🇭 +41</span>');
    }

    public function test_un_mobile_etranger_est_enregistre(): void
    {
        $membre = User::factory()->create();

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->call('modifier')
            ->set('mobile_pays', 'FR')
            ->set('mobile', '06 12 34 56 78')
            ->call('enregistrerCoordonnees')
            ->assertHasNoErrors();

        $this->assertSame('+33 6 12 34 56 78', $membre->fresh()->mobile);

        Livewire::actingAs($membre->fresh())
            ->test(MonProfil::class)
            ->assertSet('mobile_pays', 'FR')
            ->assertSet('mobile', '6 12 34 56 78');
    }

    public function test_un_pays_inconnu_est_refuse(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MonProfil::class)
            ->call('modifier')
            ->set('mobile_pays', 'ZZ')
            ->call('enregistrerCoordonnees')
            ->assertHasErrors('mobile_pays');
    }
}
