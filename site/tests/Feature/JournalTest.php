<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Administration\JournalModifications;
use App\Livewire\Membres\FicheMembre;
use App\Livewire\Site\GestionPartenaires;
use App\Models\JournalModification;
use App\Models\Partenaire;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private User $administrateur;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->administrateur = User::factory()->avecRole(Role::Administrateur)->create(['prenom' => 'Web', 'nom' => 'Master']);
        $this->actingAs($this->administrateur);
    }

    private function derniereEntree(): JournalModification
    {
        return JournalModification::latest('id')->firstOrFail();
    }

    public function test_une_modification_est_inscrite_avec_l_auteur_et_les_valeurs(): void
    {
        $partenaire = Partenaire::firstWhere('nom', 'COOP');

        Livewire::test(GestionPartenaires::class)
            ->call('modifier', $partenaire->id)
            ->set('nom', 'Coop Echallens')
            ->call('enregistrer');

        $entree = $this->derniereEntree();

        $this->assertSame(JournalModification::MODIFICATION, $entree->action);
        $this->assertSame($this->administrateur->id, $entree->user_id);
        $this->assertSame('Partenaire « Coop Echallens »', $entree->libelle);
        $this->assertSame(['nom' => 'COOP'], $entree->anciennes_valeurs);
        $this->assertSame(['nom' => 'Coop Echallens'], $entree->nouvelles_valeurs);
        $this->assertNotNull($entree->ip);
    }

    public function test_creation_et_suppression_sont_inscrites(): void
    {
        $partenaire = Partenaire::factory()->create(['nom' => 'Temporaire']);
        $this->assertSame(JournalModification::CREATION, $this->derniereEntree()->action);

        $partenaire->delete();
        $entree = $this->derniereEntree();

        $this->assertSame(JournalModification::SUPPRESSION, $entree->action);
        $this->assertSame('Temporaire', $entree->anciennes_valeurs['nom']);
    }

    public function test_le_mot_de_passe_n_est_jamais_conserve(): void
    {
        $membre = User::factory()->create();
        $membre->forceFill(['password' => 'Nouveau-Secret42!'])->save();

        $entree = $this->derniereEntree();

        $this->assertArrayHasKey('password', $entree->nouvelles_valeurs);
        $this->assertNull($entree->nouvelles_valeurs['password']);
        $this->assertNull($entree->anciennes_valeurs['password']);
    }

    public function test_les_champs_techniques_ne_creent_pas_d_entree(): void
    {
        $membre = User::factory()->create();
        $avant = JournalModification::count();

        $membre->genererCodeConnexion();
        $membre->forceFill(['derniere_connexion_le' => now()])->save();

        $this->assertSame($avant, JournalModification::count());
    }

    public function test_restaurer_une_modification(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('localite', 'Echallens')
            ->call('enregistrer');

        app(Journal::class)->restaurer($this->derniereEntree());

        $this->assertSame('Bercher', $membre->fresh()->localite);
        $this->assertSame('Restauration de l\'entrée n° '.($this->derniereEntree()->id - 1), $this->derniereEntree()->note);
    }

    public function test_restaurer_un_partenaire_supprime_avec_son_logo(): void
    {
        Livewire::test(GestionPartenaires::class)
            ->call('ajouter')
            ->set('nom', 'Boulangerie')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 100))
            ->call('enregistrer');

        $partenaire = Partenaire::firstWhere('nom', 'Boulangerie');
        $logo = $partenaire->logo;

        Livewire::test(GestionPartenaires::class)->call('supprimer', $partenaire->id);

        Storage::disk('local')->assertMissing('logos-partenaires/'.$logo);
        $suppression = $this->derniereEntree();
        $this->assertCount(1, $suppression->fichiers);

        Livewire::test(JournalModifications::class)
            ->call('restaurer', $suppression->id)
            ->assertSet('erreur', null);

        $restaure = Partenaire::find($partenaire->id);
        $this->assertNotNull($restaure);
        $this->assertSame($logo, $restaure->logo);
        Storage::disk('local')->assertExists('logos-partenaires/'.$logo);
    }

    public function test_restaurer_une_photo_remplacee_sous_le_meme_nom(): void
    {
        $membre = User::factory()->create(['nom' => 'Rochat', 'prenom' => 'Aline']);

        foreach ([[300, 300], [500, 500]] as [$largeur, $hauteur]) {
            Livewire::test(FicheMembre::class, ['membre' => $membre->fresh()])
                ->call('modifier')
                ->set('photo', UploadedFile::fake()->image('photo.jpg', $largeur, $hauteur))
                ->call('enregistrer');
        }

        $chemin = 'photos-membres/FSG-Echallens_Rochat_Aline.jpg';
        $this->assertSame(500, getimagesize(Storage::disk('local')->path($chemin))[0]);

        $remplacement = $this->derniereEntree();
        $this->assertSame(['photo'], array_keys($remplacement->nouvelles_valeurs));
        $this->assertCount(1, $remplacement->fichiers);

        app(Journal::class)->restaurer($remplacement);

        $this->assertSame(300, getimagesize(Storage::disk('local')->path($chemin))[0], 'L\'ancienne photo est revenue.');
        $this->assertCount(1, $this->derniereEntree()->fichiers, 'La photo remplacée par la restauration est elle-même archivée.');
    }

    public function test_restauration_impossible_si_l_element_existe_deja(): void
    {
        $partenaire = Partenaire::factory()->create();
        $partenaire->delete();
        $suppression = $this->derniereEntree();

        app(Journal::class)->restaurer($suppression);

        Livewire::test(JournalModifications::class)
            ->call('restaurer', $suppression->id)
            ->assertSet('erreur', 'Cet élément existe déjà : il a sans doute déjà été restauré.');
    }

    public function test_le_journal_est_reserve_a_l_administrateur(): void
    {
        $this->get(route('journal.index'))->assertOk()->assertSee('Journal des modifications');

        $this->actingAs(User::factory()->avecRole(Role::Comite)->create())
            ->get(route('journal.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('journal.index'))->assertRedirect(route('login'));
    }

    public function test_le_n_avs_est_masque_dans_le_journal(): void
    {
        $membre = User::factory()->create(['avs' => '756.1234.5678.97']);

        Livewire::test(JournalModifications::class)
            ->call('basculer', JournalModification::where('sujet_id', $membre->id)->where('sujet_type', User::class)->value('id'))
            ->assertSee('•••• (masqué)')
            ->assertDontSee('756.1234.5678.97');
    }

    public function test_nettoyage_apres_la_duree_de_conservation(): void
    {
        config(['journal.conservation_jours' => 30]);
        Storage::disk('local')->put('archives-journal/vieux.png', 'png');
        $recente = $this->derniereEntree();

        $vieille = JournalModification::create([
            'action' => JournalModification::MODIFICATION, 'sujet_type' => Partenaire::class, 'sujet_id' => 1,
            'libelle' => 'Ancienne', 'fichiers' => [['attribut' => 'logo', 'disque' => 'local', 'chemin' => 'logos-partenaires/x.png', 'archive' => 'archives-journal/vieux.png']],
        ]);
        $vieille->forceFill(['created_at' => now()->subDays(31)])->save();

        $this->artisan('model:prune', ['--model' => [JournalModification::class]])->assertSuccessful();

        $this->assertModelMissing($vieille);
        $this->assertModelExists($recente);
        Storage::disk('local')->assertMissing('archives-journal/vieux.png');
    }

    public function test_un_mot_de_passe_reinitialise_reste_utilisable(): void
    {
        $membre = User::factory()->create(['password' => 'Ancien-Secret42!']);
        $membre->forceFill(['password' => 'Nouveau-Secret42!'])->save();

        $this->assertTrue(Hash::check('Nouveau-Secret42!', $membre->fresh()->password));
    }
}
