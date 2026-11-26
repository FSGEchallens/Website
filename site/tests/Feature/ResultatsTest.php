<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\GestionResultats;
use App\Models\JournalModification;
use App\Models\Resultat;
use App\Models\User;
use App\Services\Journal;
use App\Services\PhotosResultat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ResultatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function moniteur(): User
    {
        return User::factory()->avecRole(Role::Moniteur)->create();
    }

    public function test_page_publique_du_plus_recent_au_plus_ancien(): void
    {
        Resultat::factory()->create(['date' => '2019-03-16', 'titre' => 'Concours ancien']);
        Resultat::factory()->create(['date' => '2022-04-02', 'titre' => 'Concours récent']);
        Resultat::factory()->create(['date' => '2021-12-11', 'titre' => 'Concours masqué', 'visible' => false]);

        $this->get(route('resultats.index'))
            ->assertOk()
            ->assertSeeInOrder(['Concours récent', 'Concours ancien'])
            ->assertSee('2 avril 2022')
            ->assertDontSee('Concours masqué')
            ->assertDontSee('Ajouter un résultat');
    }

    public function test_dates_sur_plusieurs_jours(): void
    {
        $this->assertSame('14-15 septembre 2019', Resultat::factory()->make(['date' => '2019-09-14', 'date_fin' => '2019-09-15'])->dateLisible());
        $this->assertSame('30 mai - 2 juin 2019', Resultat::factory()->make(['date' => '2019-05-30', 'date_fin' => '2019-06-02'])->dateLisible());
        $this->assertSame('11 décembre 2021', Resultat::factory()->make(['date' => '2021-12-11'])->dateLisible());
    }

    public function test_le_menu_pointe_vers_la_nouvelle_page(): void
    {
        $this->get(route('accueil'))->assertSee(route('resultats.index'))->assertDontSee('gymechallens.ch/resultat/');
    }

    public function test_un_moniteur_publie_un_resultat_avec_photos(): void
    {
        $moniteur = $this->moniteur();
        $this->actingAs($moniteur)->get(route('resultats.index'))->assertSee('Ajouter un résultat');

        Livewire::actingAs($moniteur)
            ->test(GestionResultats::class)
            ->call('ajouter')
            ->set('date', '2026-03-14')
            ->set('date_fin', '2026-03-15')
            ->set('titre', 'Master 1 à Moudon')
            ->set('contenu', '<ul><li>C3 fille, <b>3e place</b></li></ul><script>x</script>')
            ->set('nouvellesPhotos', [
                UploadedFile::fake()->image('a.jpg', 3000, 2000),
                UploadedFile::fake()->image('b.png', 800, 600),
            ])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $resultat = Resultat::firstOrFail();
        $this->assertSame('<ul><li>C3 fille, <strong>3e place</strong></li></ul>', $resultat->contenu);
        $this->assertCount(2, $resultat->photos);

        $chemin = 'resultats/photos/'.$resultat->photos[0];
        Storage::disk('local')->assertExists($chemin);
        Storage::disk('local')->assertExists(PhotosResultat::nomMiniature($chemin));
        $this->assertSame(1600, getimagesize(Storage::disk('local')->path($chemin))[0]);
        $this->assertSame(500, getimagesize(Storage::disk('local')->path(PhotosResultat::nomMiniature($chemin)))[0]);

        $this->get(route('resultats.index'))->assertSee('14-15 mars 2026')->assertSee($resultat->urlPhoto(0, miniature: true));
        $this->get($resultat->urlPhoto(1))->assertOk();
    }

    public function test_dix_photos_au_maximum(): void
    {
        $fichiers = array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg", 50, 50), range(1, 11));

        Livewire::actingAs($this->moniteur())
            ->test(GestionResultats::class)
            ->call('ajouter')
            ->set('titre', 'Trop de photos')
            ->set('nouvellesPhotos', $fichiers)
            ->call('enregistrer')
            ->assertHasErrors('nouvellesPhotos');

        $this->assertSame(0, Resultat::count());
    }

    public function test_retirer_et_reordonner_les_photos(): void
    {
        $moniteur = $this->moniteur();

        Livewire::actingAs($moniteur)
            ->test(GestionResultats::class)
            ->call('ajouter')
            ->set('titre', 'Concours')
            ->set('nouvellesPhotos', array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg", 60, 60), [1, 2, 3]))
            ->call('enregistrer');

        $resultat = Resultat::firstOrFail();
        [$premiere, $deuxieme, $troisieme] = $resultat->photos;

        Livewire::actingAs($moniteur)
            ->test(GestionResultats::class)
            ->call('modifier', $resultat->id)
            ->call('retirerPhoto', 0)
            ->call('deplacerPhoto', 1, -1)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame([$troisieme, $deuxieme], $resultat->fresh()->photos);
        Storage::disk('local')->assertMissing('resultats/photos/'.$premiere);
        $this->assertNotEmpty(JournalModification::latest('id')->first()->fichiers, 'La photo retirée est archivée.');
    }

    public function test_une_photo_d_un_autre_resultat_ne_peut_pas_etre_reprise(): void
    {
        Resultat::factory()->create(['photos' => ['secret.jpg']]);
        $resultat = Resultat::factory()->create();

        Livewire::actingAs($this->moniteur())
            ->test(GestionResultats::class)
            ->call('modifier', $resultat->id)
            ->set('photos', ['secret.jpg'])
            ->call('enregistrer');

        $this->assertNull($resultat->fresh()->photos);
    }

    public function test_acces_reserve(): void
    {
        $this->get(route('resultats.gestion'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->avecRole(Role::Membre)->create())->get(route('resultats.gestion'))->assertForbidden();
        $this->actingAs($this->moniteur())->get(route('resultats.gestion'))->assertOk();
        $this->actingAs(User::factory()->avecRole(Role::Comite)->create())->get(route('resultats.gestion'))->assertOk();
    }

    public function test_photo_d_un_resultat_masque_non_publique(): void
    {
        Storage::disk('local')->put('resultats/photos/x.jpg', 'jpg');
        $resultat = Resultat::factory()->create(['visible' => false, 'photos' => ['x.jpg']]);

        $this->get($resultat->urlPhoto(0))->assertNotFound();
        $this->actingAs($this->moniteur())->get($resultat->urlPhoto(0))->assertOk();
    }

    public function test_suppression_restaurable_avec_ses_photos(): void
    {
        $moniteur = $this->moniteur();

        Livewire::actingAs($moniteur)
            ->test(GestionResultats::class)
            ->call('ajouter')
            ->set('titre', 'À supprimer')
            ->set('nouvellesPhotos', [UploadedFile::fake()->image('p.jpg', 80, 80)])
            ->call('enregistrer');

        $resultat = Resultat::firstOrFail();
        $photo = $resultat->photos[0];

        Livewire::actingAs($moniteur)->test(GestionResultats::class)->call('supprimer', $resultat->id);
        Storage::disk('local')->assertMissing('resultats/photos/'.$photo);

        app(Journal::class)->restaurer(JournalModification::latest('id')->firstOrFail());

        $this->assertNotNull(Resultat::find($resultat->id));
        Storage::disk('local')->assertExists('resultats/photos/'.$photo);
        $this->get(Resultat::find($resultat->id)->urlPhoto(0, miniature: true))->assertOk();
    }
}
