<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Galerie\GestionAlbum;
use App\Models\Album;
use App\Models\JournalModification;
use App\Models\PhotoGalerie;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GalerieTest extends TestCase
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

    /**
     * Album avec des photos réellement enregistrées (via l'envoi Livewire).
     */
    private function albumAvecPhotos(int $nombre = 2): Album
    {
        $moniteur = $this->moniteur();
        $album = Album::factory()->create(['titre' => 'Spectacle 2026', 'slug' => 'spectacle-2026']);
        $composant = Livewire::actingAs($moniteur)->test(GestionAlbum::class, ['album' => $album]);

        for ($i = 1; $i <= $nombre; $i++) {
            $composant->set('photoEnvoyee', UploadedFile::fake()->image("IMG_$i.jpg", 2400, 1600));
        }

        return $album->fresh();
    }

    public function test_la_liste_des_albums_du_plus_recent_au_plus_ancien(): void
    {
        Album::factory()->create(['titre' => 'Fondue 2022', 'slug' => 'fondue-2022', 'date' => '2022-02-01']);
        Album::factory()->create(['titre' => 'Spectacle 2025', 'slug' => 'spectacle-2025', 'date' => '2025-03-22']);
        Album::factory()->create(['titre' => 'En préparation', 'slug' => 'en-preparation', 'visible' => false]);

        $this->get(route('galerie.index'))
            ->assertOk()
            ->assertSeeInOrder(['Spectacle 2025', 'Fondue 2022'])
            ->assertDontSee('En préparation')
            ->assertDontSee('Nouvel album');

        $this->actingAs($this->moniteur())->get(route('galerie.index'))
            ->assertSee('Nouvel album')
            ->assertSee('En préparation');

        $this->get(route('accueil'))->assertSee(route('galerie.index'));
    }

    public function test_creer_un_album(): void
    {
        Livewire::actingAs($this->moniteur())
            ->test(GestionAlbum::class)
            ->set('titre', 'Fête de fin d’année')
            ->set('date', '2026-06-20')
            ->call('enregistrerAlbum')
            ->assertHasNoErrors()
            ->assertRedirect(route('galerie.gestion', 'fete-de-fin-dannee'));

        $this->assertSame('fete-de-fin-dannee', Album::firstOrFail()->slug);
    }

    public function test_deux_albums_du_meme_nom_ont_des_adresses_differentes(): void
    {
        Album::factory()->create(['titre' => 'Spectacle', 'slug' => 'spectacle']);

        $this->assertSame('spectacle-2', Album::slugUnique('Spectacle'));
    }

    public function test_ajout_de_photos_reduites_avec_miniatures(): void
    {
        $album = $this->albumAvecPhotos(2);

        $this->assertSame(2, $album->photos()->count());
        $photo = $album->photos()->first();

        Storage::disk('local')->assertExists($photo->chemin());
        Storage::disk('local')->assertExists($photo->chemin(miniature: true));
        $this->assertSame(1920, getimagesize(Storage::disk('local')->path($photo->chemin()))[0]);
        $this->assertSame(480, getimagesize(Storage::disk('local')->path($photo->chemin(miniature: true)))[0]);
        $this->assertSame('IMG_1.jpg', $photo->nom_original);

        $this->get(route('galerie.album', $album))
            ->assertOk()
            ->assertSee($photo->url(miniature: true))
            ->assertSee('visionneuse', false)
            ->assertSee('Diaporama');

        $this->get($photo->url())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        $album = Album::factory()->create();

        Livewire::actingAs($this->moniteur())
            ->test(GestionAlbum::class, ['album' => $album])
            ->set('photoEnvoyee', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
            ->assertSet('erreursEnvoi', ['« document.pdf » n\'est pas une image JPEG, PNG ou WebP.']);

        $this->assertSame(0, $album->photos()->count());
    }

    public function test_limite_de_999_photos(): void
    {
        $album = Album::factory()->create();
        PhotoGalerie::factory()->count(Album::PHOTOS_MAX)->create(['album_id' => $album->id]);

        Livewire::actingAs($this->moniteur())
            ->test(GestionAlbum::class, ['album' => $album])
            ->set('photoEnvoyee', UploadedFile::fake()->image('une-de-trop.jpg', 100, 100))
            ->assertCount('erreursEnvoi', 1);

        $this->assertSame(Album::PHOTOS_MAX, $album->photos()->count());
    }

    public function test_couverture_et_suppression_d_une_photo(): void
    {
        $album = $this->albumAvecPhotos(2);
        [$premiere, $deuxieme] = $album->photos()->get();

        Livewire::actingAs($this->moniteur())
            ->test(GestionAlbum::class, ['album' => $album])
            ->call('choisirCouverture', $deuxieme->id)
            ->call('supprimerPhoto', $premiere->id);

        $album->refresh();
        $this->assertSame($deuxieme->id, $album->couverture_id);
        $this->assertSame(1, $album->photos()->count());
        $this->assertSoftDeleted($premiere);
        // Le fichier reste disponible tant que la photo est dans la corbeille.
        Storage::disk('local')->assertExists($premiere->chemin());
        $this->get($premiere->url())->assertNotFound();
    }

    public function test_supprimer_puis_restaurer_un_album_avec_ses_photos(): void
    {
        $album = $this->albumAvecPhotos(3);

        Livewire::actingAs($this->moniteur())
            ->test(GestionAlbum::class, ['album' => $album])
            ->call('supprimerAlbum')
            ->assertRedirect(route('galerie.index'));

        $this->assertSoftDeleted($album);
        $this->assertSame(3, PhotoGalerie::onlyTrashed()->where('album_id', $album->id)->count());
        $this->get(route('galerie.album', $album->slug))->assertNotFound();

        $suppression = JournalModification::where('sujet_type', Album::class)->where('action', JournalModification::SUPPRESSION)->firstOrFail();
        app(Journal::class)->restaurer($suppression);

        $this->assertNotSoftDeleted($album);
        $this->assertSame(3, $album->fresh()->photos()->count(), 'Les photos reviennent avec l\'album.');
    }

    public function test_nettoyage_de_la_corbeille_apres_la_duree_de_conservation(): void
    {
        $album = $this->albumAvecPhotos(1);
        $photo = $album->photos()->first();
        $photo->delete();

        $this->travel((int) config('journal.conservation_jours') + 1)->days();
        $this->artisan('model:prune', ['--model' => [PhotoGalerie::class]])->assertSuccessful();

        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing($photo->chemin());
    }

    public function test_album_non_publie_invisible_pour_le_public(): void
    {
        $album = Album::factory()->create(['visible' => false, 'slug' => 'secret']);
        $photo = PhotoGalerie::factory()->create(['album_id' => $album->id]);
        Storage::disk('local')->put($photo->chemin(), 'jpg');

        $this->get(route('galerie.album', $album))->assertNotFound();
        $this->get($photo->url())->assertNotFound();

        $this->actingAs($this->moniteur())->get(route('galerie.album', $album))->assertOk();
    }

    public function test_acces_a_la_gestion(): void
    {
        $album = Album::factory()->create();

        $this->get(route('galerie.nouvel-album'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->avecRole(Role::Membre)->create())->get(route('galerie.gestion', $album))->assertForbidden();
        $this->actingAs($this->moniteur())->get(route('galerie.gestion', $album))->assertOk();
    }
}
