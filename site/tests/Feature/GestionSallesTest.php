<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Cours\GestionSalles;
use App\Models\Batiment;
use App\Models\Cours;
use App\Models\JournalModification;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GestionSallesTest extends TestCase
{
    use RefreshDatabase;

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    public function test_la_page_est_reservee_au_comite(): void
    {
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())->get(route('salles.gestion'))->assertForbidden();
        $this->actingAs($this->comite())->get(route('salles.gestion'))->assertOk()->assertSee('Bâtiments et salles');
    }

    public function test_creer_un_batiment_avec_ses_salles_adresse_photos_et_carte(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->comite())
            ->test(GestionSalles::class)
            ->call('ajouter')
            ->set('nom', 'Echallens, Court-Champ')
            ->set('sallesInitiales', "Salle 3\nSalle 4\n\nSalle 3")
            ->set('rue', 'Chemin du Collège 3')
            ->set('npa', '1040')
            ->set('localite', 'Echallens')
            ->assertSee('maps.google.com/maps?q=Chemin%20du%20Coll%C3%A8ge%203%2C%201040%20Echallens', false)
            ->set('informations', 'Entrée côté parking')
            ->set('nouvellesPhotos', [UploadedFile::fake()->image('batiment.jpg', 2400, 1800), UploadedFile::fake()->image('entree.png', 800, 600)])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $batiment = Batiment::firstOrFail();
        $this->assertSame('Chemin du Collège 3, 1040 Echallens', $batiment->adresseComplete());
        $this->assertSame(['Salle 3', 'Salle 4'], $batiment->salles->pluck('nom')->all());
        $this->assertCount(2, $batiment->photos);
        Storage::disk('local')->assertExists('batiments/'.$batiment->photos[0]);

        [$largeur] = getimagesizefromstring(Storage::disk('local')->get('batiments/'.$batiment->photos[0]));
        $this->assertSame(1600, $largeur);

        $this->get($batiment->urlPhoto(1, miniature: true))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('google.com/maps/search', (string) $batiment->urlCarte());
        $this->assertSame('Echallens, Court-Champ – Salle 4', $batiment->salles[1]->libelleComplet());
    }

    public function test_un_nouveau_batiment_doit_avoir_une_salle_et_un_npa_valable(): void
    {
        Livewire::actingAs($this->comite())
            ->test(GestionSalles::class)
            ->call('ajouter')
            ->set('nom', 'Bâtiment')
            ->set('sallesInitiales', '')
            ->set('npa', '10a')
            ->call('enregistrer')
            ->assertHasErrors('npa')
            ->set('npa', '1040')
            ->call('enregistrer')
            ->assertHasErrors('sallesInitiales');
    }

    public function test_ajouter_renommer_et_supprimer_des_salles(): void
    {
        $batiment = Batiment::factory()->create();
        $utilisee = Salle::factory()->for($batiment)->create(['nom' => 'Salle 1']);
        Cours::factory()->create(['salles' => [$utilisee->id]]);

        Livewire::actingAs($this->comite())
            ->test(GestionSalles::class)
            ->set('nouvelleSalle.'.$batiment->id, 'Salle 2')
            ->call('ajouterSalle', $batiment->id)
            ->assertHasNoErrors()
            ->set('nouvelleSalle.'.$batiment->id, 'Salle 2')
            ->call('ajouterSalle', $batiment->id)
            ->assertHasErrors('nouvelleSalle.'.$batiment->id)
            ->call('renommerSalle', $utilisee->id)
            ->set('nomSalle', 'Grande salle')
            ->call('enregistrerSalle')
            ->assertHasNoErrors()
            ->call('supprimerSalle', $utilisee->id)
            ->assertSee('est utilisée par des cours')
            ->call('supprimer', $batiment->id)
            ->assertSee('Supprimez d’abord les salles');

        $this->assertSame(['Grande salle', 'Salle 2'], $batiment->salles()->pluck('nom')->all());

        $libre = Salle::where('nom', 'Salle 2')->sole();
        Livewire::actingAs($this->comite())->test(GestionSalles::class)->call('supprimerSalle', $libre->id);
        $this->assertSame(1, Salle::count());
    }

    public function test_au_maximum_3_photos_et_une_photo_retiree_est_archivee(): void
    {
        Storage::fake('local');
        $comite = $this->comite();
        $photo = fn () => UploadedFile::fake()->image('p.jpg', 400, 300);

        Livewire::actingAs($comite)
            ->test(GestionSalles::class)
            ->call('ajouter')
            ->set('nom', 'Bâtiment test')
            ->set('nouvellesPhotos', [$photo(), $photo(), $photo(), $photo()])
            ->call('enregistrer')
            ->assertHasErrors('nouvellesPhotos');

        $composant = Livewire::actingAs($comite)
            ->test(GestionSalles::class)
            ->call('ajouter')
            ->set('nom', 'Bâtiment test')
            ->set('nouvellesPhotos', [$photo(), $photo(), $photo()])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $batiment = Batiment::firstOrFail();
        $this->assertCount(3, $batiment->photos);
        $retiree = $batiment->photos[0];

        $composant->call('modifier', $batiment->id)
            ->call('retirerPhoto', 0)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertCount(2, $batiment->fresh()->photos);
        Storage::disk('local')->assertMissing('batiments/'.$retiree);
        $this->assertNotEmpty(JournalModification::latest('id')->first()->fichiers);
    }
}
