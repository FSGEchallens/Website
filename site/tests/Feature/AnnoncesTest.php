<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\GestionAnnonces;
use App\Models\Annonce;
use App\Models\JournalModification;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AnnoncesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    public function test_creer_une_annonce_complete(): void
    {
        Livewire::actingAs($this->comite())
            ->test(GestionAnnonces::class)
            ->call('ajouter')
            ->set('titre', 'Venez fêter nos 120 ans !')
            ->set('contenu', '<p><b>Grand spectacle</b> le 12 mars<script>alert(1)</script></p>')
            ->set('image', UploadedFile::fake()->image('affiche.jpg', 2400, 1600))
            ->set('position_image', 'gauche')
            ->call('ajouterLien')
            ->set('liens.0.libelle', 'Réserver mes places')
            ->set('liens.0.url', 'https://billetterie.exemple.ch')
            ->call('ajouterLien')
            ->set('liens.1.libelle', 'Flyer (PDF)')
            ->set('liens.1.type', 'fichier')
            ->set('fichiersLiens.1', UploadedFile::fake()->create('flyer.pdf', 200, 'application/pdf'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $annonce = Annonce::firstOrFail();

        $this->assertSame('<p><strong>Grand spectacle</strong> le 12 mars</p>', $annonce->contenu);
        $this->assertSame('gauche', $annonce->position_image);
        Storage::disk('local')->assertExists('annonces/images/'.$annonce->image);
        $this->assertLessThanOrEqual(1600, getimagesize(Storage::disk('local')->path('annonces/images/'.$annonce->image))[0]);
        $this->assertSame('url', $annonce->liens[0]['type']);
        $this->assertSame('fichier', $annonce->liens[1]['type']);
        $this->assertSame('flyer.pdf', $annonce->liens[1]['nom_original']);
        Storage::disk('local')->assertExists('annonces/fichiers/'.$annonce->liens[1]['fichier']);

        auth()->logout();
        $this->get(route('accueil'))
            ->assertSee('Venez fêter nos 120 ans !')
            ->assertSee('https://billetterie.exemple.ch')
            ->assertSee(route('annonces.fichier', [$annonce, 1]))
            ->assertSee('annonce--image-gauche', false);

        $this->get(route('annonces.image', $annonce))->assertOk();
        $this->get(route('annonces.fichier', [$annonce, 1]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_validation(): void
    {
        Livewire::actingAs($this->comite())
            ->test(GestionAnnonces::class)
            ->call('ajouter')
            ->set('titre', '')
            ->set('debut_le', '2026-12-10')
            ->set('fin_le', '2026-12-01')
            ->call('ajouterLien')
            ->set('liens.0.libelle', '')
            ->set('liens.0.url', 'javascript:alert(1)')
            ->call('enregistrer')
            ->assertHasErrors(['titre', 'fin_le', 'liens.0.libelle', 'liens.0.url']);

        Livewire::actingAs($this->comite())
            ->test(GestionAnnonces::class)
            ->call('ajouter')
            ->set('titre', 'Test')
            ->call('ajouterLien')
            ->set('liens.0.libelle', 'Fichier')
            ->set('liens.0.type', 'fichier')
            ->call('enregistrer')
            ->assertHasErrors('fichiersLiens.0');

        $this->assertSame(0, Annonce::count());
    }

    public function test_periode_d_affichage(): void
    {
        Annonce::factory()->create(['titre' => 'Passée', 'debut_le' => now()->subDays(10), 'fin_le' => now()->subDay()]);
        Annonce::factory()->create(['titre' => 'En cours', 'debut_le' => now()->subDay(), 'fin_le' => now()]);
        Annonce::factory()->create(['titre' => 'Future', 'debut_le' => now()->addDay()]);
        Annonce::factory()->create(['titre' => 'Masquée', 'visible' => false]);

        $this->get(route('accueil'))
            ->assertSee('En cours')
            ->assertDontSee('Passée')
            ->assertDontSee('Future')
            ->assertDontSee('Masquée');
    }

    public function test_le_comite_voit_les_boutons_et_pas_les_visiteurs(): void
    {
        Annonce::factory()->create(['titre' => 'Spectacle']);

        $this->get(route('accueil'))->assertDontSee('Ajouter une annonce');
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())->get(route('accueil'))->assertDontSee('Ajouter une annonce');

        $this->actingAs($this->comite())->get(route('accueil'))
            ->assertSee('Ajouter une annonce')
            ->assertSee('✎ Modifier');
    }

    public function test_la_gestion_est_reservee_au_comite(): void
    {
        $this->get(route('annonces.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())->get(route('annonces.index'))->assertForbidden();
        $this->actingAs($this->comite())->get(route('annonces.index', ['modifier' => 999]))->assertOk();
    }

    public function test_les_fichiers_d_une_annonce_masquee_ne_sont_pas_publics(): void
    {
        Storage::disk('local')->put('annonces/images/a.jpg', 'jpg');
        $annonce = Annonce::factory()->create(['visible' => false, 'image' => 'a.jpg']);

        $this->get(route('annonces.image', $annonce))->assertNotFound();
        $this->actingAs($this->comite())->get(route('annonces.image', $annonce))->assertOk();
    }

    public function test_un_fichier_d_une_autre_annonce_ne_peut_pas_etre_repris(): void
    {
        $autre = Annonce::factory()->create(['liens' => [['libelle' => 'x', 'type' => 'fichier', 'fichier' => 'secret.pdf', 'nom_original' => 'secret.pdf']]]);
        $annonce = Annonce::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(GestionAnnonces::class)
            ->call('modifier', $annonce->id)
            ->call('ajouterLien')
            ->set('liens.0.libelle', 'Volé')
            ->set('liens.0.type', 'fichier')
            ->set('liens.0.fichier', 'secret.pdf')
            ->call('enregistrer')
            ->assertHasErrors('fichiersLiens.0');

        $this->assertNull($annonce->fresh()->liens);
        $this->assertNotNull($autre->fresh()->liens);
    }

    public function test_retirer_un_fichier_l_archive_et_la_suppression_est_restaurable(): void
    {
        $comite = $this->comite();

        Livewire::actingAs($comite)
            ->test(GestionAnnonces::class)
            ->call('ajouter')
            ->set('titre', 'Soirée')
            ->call('ajouterLien')
            ->set('liens.0.libelle', 'Flyer')
            ->set('liens.0.type', 'fichier')
            ->set('fichiersLiens.0', UploadedFile::fake()->create('flyer.pdf', 50, 'application/pdf'))
            ->call('enregistrer');

        $annonce = Annonce::firstOrFail();
        $fichier = $annonce->liens[0]['fichier'];

        Livewire::actingAs($comite)
            ->test(GestionAnnonces::class)
            ->call('supprimer', $annonce->id);

        $this->assertModelMissing($annonce);
        Storage::disk('local')->assertMissing('annonces/fichiers/'.$fichier);

        $suppression = JournalModification::latest('id')->firstOrFail();
        $this->assertSame(JournalModification::SUPPRESSION, $suppression->action);
        $this->assertCount(1, $suppression->fichiers);

        app(Journal::class)->restaurer($suppression);

        $this->assertNotNull(Annonce::find($annonce->id));
        Storage::disk('local')->assertExists('annonces/fichiers/'.$fichier);
    }

    public function test_masquer_et_changer_l_ordre(): void
    {
        $premiere = Annonce::factory()->create(['ordre' => 1]);
        $deuxieme = Annonce::factory()->create(['ordre' => 2]);

        Livewire::actingAs($this->comite())
            ->test(GestionAnnonces::class)
            ->call('deplacer', $deuxieme->id, -1)
            ->call('basculerVisibilite', $premiere->id);

        $this->assertSame($deuxieme->id, Annonce::dansLOrdre()->first()->id);
        $this->assertFalse($premiere->fresh()->visible);
    }
}
