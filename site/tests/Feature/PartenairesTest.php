<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\GestionPartenaires;
use App\Models\Partenaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PartenairesTest extends TestCase
{
    use RefreshDatabase;

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    public function test_les_partenaires_d_origine_sont_repris(): void
    {
        $this->assertSame(9, Partenaire::count());
        $this->assertSame('FSG – Fédération suisse de gymnastique', Partenaire::dansLOrdre()->first()->nom);

        $this->get(route('accueil'))
            ->assertSee('images/partenaires/FSG_logo.jpg', false)
            ->assertSee('WEINMANN Energies');
    }

    public function test_le_bouton_de_modification_est_reserve_au_comite(): void
    {
        $this->get(route('accueil'))->assertDontSee('Modifier les partenaires');

        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->get(route('accueil'))->assertDontSee('Modifier les partenaires');

        $this->actingAs($this->comite())
            ->get(route('accueil'))->assertSee('Modifier les partenaires');
    }

    public function test_la_gestion_est_reservee_au_comite_et_a_l_administrateur(): void
    {
        $this->get(route('partenaires.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->get(route('partenaires.index'))->assertForbidden();

        $this->actingAs($this->comite())->get(route('partenaires.index'))->assertOk();
        $this->actingAs(User::factory()->avecRole(Role::Administrateur)->create())
            ->get(route('partenaires.index'))->assertOk();
    }

    public function test_ajout_d_un_partenaire_avec_logo(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('ajouter')
            ->set('nom', 'Boulangerie du Bourg')
            ->set('url', 'https://www.boulangerie-exemple.ch')
            ->set('logo', UploadedFile::fake()->image('logo.jpg', 1200, 600))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $partenaire = Partenaire::firstWhere('nom', 'Boulangerie du Bourg');

        $this->assertSame(10, $partenaire->ordre, 'Ajouté à la fin de la liste.');
        $this->assertStringEndsWith('.png', $partenaire->logo);
        Storage::disk('local')->assertExists('logos-partenaires/'.$partenaire->logo);

        [$largeur, $hauteur] = getimagesize(Storage::disk('local')->path('logos-partenaires/'.$partenaire->logo));
        $this->assertLessThanOrEqual(400, $largeur);
        $this->assertLessThanOrEqual(160, $hauteur);

        $this->get(route('partenaires.logo', $partenaire))->assertOk();
        $this->get(route('accueil'))->assertSee('Boulangerie du Bourg');
    }

    public function test_validation(): void
    {
        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('ajouter')
            ->set('nom', '')
            ->set('url', 'pas une adresse')
            ->call('enregistrer')
            ->assertHasErrors(['nom', 'url', 'logo']);

        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('ajouter')
            ->set('nom', 'Test')
            ->set('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->call('enregistrer')
            ->assertHasErrors('logo');
    }

    public function test_modification_sans_changer_le_logo(): void
    {
        $partenaire = Partenaire::dansLOrdre()->first();

        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('modifier', $partenaire->id)
            ->assertSet('nom', $partenaire->nom)
            ->set('nom', 'FSG Suisse')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $partenaire->refresh();
        $this->assertSame('FSG Suisse', $partenaire->nom);
        $this->assertSame('images/partenaires/FSG_logo.jpg', $partenaire->logo, 'Le logo d\'origine est conservé.');
    }

    public function test_masquer_un_partenaire(): void
    {
        $partenaire = Partenaire::firstWhere('nom', 'COOP');

        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('basculerVisibilite', $partenaire->id);

        $this->assertFalse($partenaire->fresh()->visible);
        $this->get(route('accueil'))->assertDontSee('images/partenaires/COOP.jpg', false);
    }

    public function test_changer_l_ordre(): void
    {
        $premier = Partenaire::dansLOrdre()->first();
        $deuxieme = Partenaire::dansLOrdre()->skip(1)->first();

        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('deplacer', $deuxieme->id, -1);

        $this->assertSame($deuxieme->id, Partenaire::dansLOrdre()->first()->id);
        $this->assertSame(2, $premier->fresh()->ordre);
    }

    public function test_suppression(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('logos-partenaires/partenaire-test.png', 'png');
        $partenaire = Partenaire::factory()->create(['logo' => 'partenaire-test.png']);

        Livewire::actingAs($this->comite())
            ->test(GestionPartenaires::class)
            ->call('supprimer', $partenaire->id);

        $this->assertModelMissing($partenaire);
        Storage::disk('local')->assertMissing('logos-partenaires/partenaire-test.png');
        $this->assertSame(range(1, 9), Partenaire::dansLOrdre()->pluck('ordre')->all(), 'L\'ordre est renuméroté.');
    }

    public function test_un_moniteur_est_refuse_par_le_composant_lui_meme(): void
    {
        Livewire::actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->test(GestionPartenaires::class)
            ->assertForbidden();

        $this->assertSame(9, Partenaire::count());
    }
}
