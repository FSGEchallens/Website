<?php

namespace Tests\Feature;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Livewire\Membres\FicheMembre;
use App\Livewire\Membres\ListeMembres;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class MembresTest extends TestCase
{
    use RefreshDatabase;

    private User $administrateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrateur = User::factory()->avecRole(Role::Administrateur)->create([
            'prenom' => 'Web', 'nom' => 'Admin', 'localite' => 'Nulle-part',
        ]);
        $this->actingAs($this->administrateur);
    }

    /**
     * Formulaire de création rempli avec les champs obligatoires.
     */
    private function nouveauMembre(): Testable
    {
        return Livewire::test(FicheMembre::class)
            ->set('civilite', 'madame')
            ->set('prenom', 'Julie')
            ->set('nom', 'Exemple')
            ->set('mobile', '079 123 45 67')
            ->set('email_prive', 'julie@example.net')
            ->set('role', 'moniteur');
    }

    public function test_la_liste_affiche_les_membres_actifs_seulement(): void
    {
        User::factory()->create(['prenom' => 'Alice', 'nom' => 'Actuelle']);
        User::factory()->create(['prenom' => 'Bernard', 'nom' => 'Parti', 'date_sortie' => now()->subYear()]);

        $this->get(route('membres.index'))
            ->assertOk()
            ->assertSee('Actuelle')
            ->assertDontSee('Parti');

        Livewire::test(ListeMembres::class)
            ->set('inclureAnciens', true)
            ->assertSee('Parti');
    }

    public function test_la_recherche_filtre_les_membres(): void
    {
        User::factory()->create(['nom' => 'Rochat', 'localite' => 'Echallens']);
        User::factory()->create(['nom' => 'Dupont', 'localite' => 'Bercher']);

        Livewire::test(ListeMembres::class)
            ->set('recherche', 'Bercher')
            ->assertSee('Dupont')
            ->assertDontSee('Rochat');
    }

    public function test_filtre_de_la_liste_par_autorisation(): void
    {
        User::factory()->avecRole(Role::Comite)->create(['nom' => 'Presidente']);
        User::factory()->avecRole(Role::Moniteur)->create(['nom' => 'Entraineur']);

        Livewire::test(ListeMembres::class)
            ->set('role', 'comite')
            ->assertSee('Presidente')
            ->assertDontSee('Entraineur');
    }

    public function test_creation_d_un_membre(): void
    {
        $this->get(route('membres.create'))->assertOk();

        $this->nouveauMembre()
            ->assertSet('enEdition', true)
            ->set('npa', '1040')
            ->set('localite', 'Echallens')
            ->set('email', 'Julie.Exemple@Example.org')
            ->set('avs', '7561234567897')
            ->set('date_naissance', '1990-05-12')
            ->set('afficher_mobile', true)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('membres.index'));

        $membre = User::where('nom', 'Exemple')->firstOrFail();

        $this->assertSame(Civilite::Madame, $membre->civilite);
        $this->assertSame(Role::Moniteur, $membre->role);
        $this->assertSame('julie.exemple@example.org', $membre->email);
        $this->assertSame('756.1234.5678.97', $membre->avs);
        $this->assertTrue($membre->afficher_mobile);
        $this->assertNull($membre->password);
        $this->assertNull($membre->rue, 'Les champs vides sont enregistrés comme NULL.');
    }

    public function test_champs_obligatoires(): void
    {
        Livewire::test(FicheMembre::class)
            ->set('role', '')
            ->call('enregistrer')
            ->assertHasErrors(['civilite', 'prenom', 'nom', 'mobile', 'email_prive', 'role']);

        $this->assertSame(1, User::count());
    }

    public function test_le_numero_avs_est_chiffre_dans_la_base(): void
    {
        $membre = User::factory()->create(['avs' => '756.1234.5678.97']);

        $brut = DB::table('users')->where('id', $membre->id)->value('avs');

        $this->assertNotSame('756.1234.5678.97', $brut);
        $this->assertStringNotContainsString('1234', $brut);
        $this->assertSame('756.1234.5678.97', $membre->fresh()->avs);
    }

    public function test_validation_des_champs(): void
    {
        User::factory()->create(['email' => 'pris@example.org']);

        $this->nouveauMembre()
            ->set('npa', '12')
            ->set('email', 'pris@example.org')
            ->set('avs', '756.1234.5678.98')
            ->set('date_entree', '2025-01-01')
            ->set('date_sortie', '2024-01-01')
            ->set('role', 'pirate')
            ->call('enregistrer')
            ->assertHasErrors(['npa', 'email', 'avs', 'date_sortie', 'role']);
    }

    public function test_une_fiche_existante_s_ouvre_verrouillee(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        $this->get(route('membres.edit', $membre))
            ->assertOk()
            ->assertSee('Bercher')
            ->assertSee('Fiche verrouillée');

        // Sans cliquer sur « Modifier », rien n'est enregistré.
        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->assertSet('enEdition', false)
            ->set('localite', 'Echallens')
            ->call('enregistrer');

        $this->assertSame('Bercher', $membre->fresh()->localite);
    }

    public function test_modification_d_un_membre(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->assertSet('enEdition', true)
            ->set('localite', 'Echallens')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Echallens', $membre->fresh()->localite);
    }

    public function test_annuler_remet_les_valeurs_d_origine(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('localite', 'Echallens')
            ->call('annuler')
            ->assertSet('enEdition', false)
            ->assertSet('localite', 'Bercher');
    }

    public function test_changement_d_autorisation(): void
    {
        $membre = User::factory()->avecRole(Role::Moniteur)->create();

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->assertSet('role', 'moniteur')
            ->call('modifier')
            ->set('role', 'comite')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $membre->refresh();
        $this->assertSame(Role::Comite, $membre->role);
        $this->assertTrue($membre->aAuMoins(Role::Moniteur), 'Le comité a aussi les droits de moniteur.');
        $this->assertFalse($membre->aAuMoins(Role::Administrateur));
    }

    public function test_un_membre_peut_garder_son_propre_e_mail(): void
    {
        $membre = User::factory()->create(['email' => 'moi@example.org']);

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('nom', 'Nouveau nom')
            ->call('enregistrer')
            ->assertHasNoErrors();
    }

    public function test_photo_importee_traitee_et_nommee(): void
    {
        Storage::fake('local');

        $this->nouveauMembre()
            ->set('nom', 'Müller')
            ->set('prenom', 'Anne-Sophie')
            ->set('photo', UploadedFile::fake()->image('vacances.jpg', 1200, 800))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $membre = User::where('nom', 'Müller')->firstOrFail();

        $this->assertSame('FSG-Echallens_Muller_Anne-Sophie.jpg', $membre->photo);
        Storage::disk('local')->assertExists('photos-membres/FSG-Echallens_Muller_Anne-Sophie.jpg');

        [$largeur, $hauteur] = getimagesize(Storage::disk('local')->path('photos-membres/'.$membre->photo));
        $this->assertSame([600, 600], [$largeur, $hauteur], 'La photo est recadrée en carré et réduite.');
    }

    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        Storage::fake('local');

        $this->nouveauMembre()
            ->set('photo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
            ->call('enregistrer')
            ->assertHasErrors('photo');
    }

    public function test_photo_renommee_si_le_nom_change_et_supprimable(): void
    {
        Storage::fake('local');
        $membre = User::factory()->create(['nom' => 'Avant', 'prenom' => 'Lea']);

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('photo', UploadedFile::fake()->image('a.png', 300, 300))
            ->call('enregistrer');

        Livewire::test(FicheMembre::class, ['membre' => $membre->fresh()])
            ->call('modifier')
            ->set('nom', 'Apres')
            ->call('enregistrer');

        $this->assertSame('FSG-Echallens_Apres_Lea.jpg', $membre->fresh()->photo);
        Storage::disk('local')->assertExists('photos-membres/FSG-Echallens_Apres_Lea.jpg');
        Storage::disk('local')->assertMissing('photos-membres/FSG-Echallens_Avant_Lea.jpg');

        Livewire::test(FicheMembre::class, ['membre' => $membre->fresh()])
            ->call('modifier')
            ->call('retirerPhoto')
            ->call('enregistrer');

        $this->assertNull($membre->fresh()->photo);
        Storage::disk('local')->assertMissing('photos-membres/FSG-Echallens_Apres_Lea.jpg');
    }

    public function test_image_par_defaut_selon_le_titre(): void
    {
        $femme = User::factory()->create(['civilite' => Civilite::Madame]);
        $homme = User::factory()->create(['civilite' => Civilite::Monsieur]);

        $this->assertStringEndsWith('images/avatars/femme.png', $femme->urlPhoto());
        $this->assertStringEndsWith('images/avatars/homme.png', $homme->urlPhoto());
    }
}
