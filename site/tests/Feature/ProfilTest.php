<?php

namespace Tests\Feature;

use App\Livewire\Profil\MonProfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_profil_s_ouvre_verrouille(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        $this->actingAs($membre)->get(route('profil'))->assertSee('Profil verrouillé');

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->assertSet('enEdition', false)
            ->set('localite', 'Echallens')
            ->call('enregistrerCoordonnees');

        $this->assertSame('Bercher', $membre->fresh()->localite);
    }

    public function test_annuler_remet_les_valeurs_d_origine(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->call('modifier')
            ->set('localite', 'Echallens')
            ->call('annuler')
            ->assertSet('enEdition', false)
            ->assertSet('localite', 'Bercher');
    }

    public function test_le_membre_change_sa_photo(): void
    {
        Storage::fake('local');
        $membre = User::factory()->create(['nom' => 'Rochat', 'prenom' => 'Aline']);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->call('modifier')
            ->set('photo', UploadedFile::fake()->image('moi.jpg', 800, 1000))
            ->call('enregistrerCoordonnees')
            ->assertHasNoErrors();

        $this->assertSame('FSG-Echallens_Rochat_Aline.jpg', $membre->fresh()->photo);
        Storage::disk('local')->assertExists('photos-membres/FSG-Echallens_Rochat_Aline.jpg');

        Livewire::actingAs($membre->fresh())
            ->test(MonProfil::class)
            ->call('modifier')
            ->call('retirerPhoto')
            ->call('enregistrerCoordonnees');

        $this->assertNull($membre->fresh()->photo);
        Storage::disk('local')->assertMissing('photos-membres/FSG-Echallens_Rochat_Aline.jpg');
    }

    public function test_un_visiteur_doit_se_connecter(): void
    {
        $this->get(route('profil'))->assertRedirect(route('login'));
    }

    public function test_tout_membre_connecte_voit_son_profil(): void
    {
        $membre = User::factory()->create([
            'prenom' => 'Julie',
            'localite' => 'Bercher',
            'avs' => '756.1234.5678.97',
        ]);

        $this->actingAs($membre)
            ->get(route('profil'))
            ->assertOk()
            ->assertSee('Julie')
            ->assertSee('Bercher')
            ->assertSee('756.****.****.97')
            ->assertDontSee('756.1234.5678.97');
    }

    public function test_le_membre_modifie_ses_coordonnees_et_son_affichage(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher', 'afficher_mobile' => false]);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->assertSet('localite', 'Bercher')
            ->call('modifier')
            ->set('localite', 'Echallens')
            ->set('npa', '1040')
            ->set('afficher_mobile', true)
            ->call('enregistrerCoordonnees')
            ->assertHasNoErrors()
            ->assertRedirect(route('profil'));

        $membre->refresh();
        $this->assertSame('Echallens', $membre->localite);
        $this->assertSame('1040', $membre->npa);
        $this->assertTrue($membre->afficher_mobile);
    }

    public function test_le_membre_ne_peut_pas_modifier_les_autres_champs(): void
    {
        $membre = User::factory()->create(['nom' => 'Original', 'email' => 'moi@example.org']);

        // Ces propriétés n'existent pas dans le composant profil : impossible de les envoyer.
        foreach (['nom' => 'Pirate', 'email' => 'pirate@example.org', 'avs' => '7561234567897', 'date_sortie' => '2020-01-01'] as $champ => $valeur) {
            try {
                Livewire::actingAs($membre)->test(MonProfil::class)->call('modifier')->set($champ, $valeur)->call('enregistrerCoordonnees');
            } catch (\Throwable) {
                // Livewire refuse une propriété inconnue : c'est le comportement attendu.
            }
        }

        $membre->refresh();
        $this->assertSame('Original', $membre->nom);
        $this->assertSame('moi@example.org', $membre->email);
        $this->assertNull($membre->date_sortie);
    }

    public function test_validation_des_coordonnees(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MonProfil::class)
            ->call('modifier')
            ->set('npa', '12')
            ->set('email_prive', 'pas-un-email')
            ->set('mobile', 'abc')
            ->call('enregistrerCoordonnees')
            ->assertHasErrors(['npa', 'email_prive', 'mobile']);
    }

    public function test_confirmation_differente_signalee_sous_la_confirmation(): void
    {
        $membre = User::factory()->create(['password' => 'Ancien-Secret42!']);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->set('mot_de_passe_actuel', 'Ancien-Secret42!')
            ->set('nouveau_mot_de_passe', 'Nouveau-Secret42!')
            ->set('nouveau_mot_de_passe_confirmation', 'Autre-Secret42!')
            ->call('changerMotDePasse')
            ->assertHasErrors(['nouveau_mot_de_passe_confirmation' => 'same'])
            ->assertHasNoErrors('nouveau_mot_de_passe');

        $this->assertTrue(Hash::check('Ancien-Secret42!', $membre->fresh()->password));
    }

    public function test_changement_de_mot_de_passe(): void
    {
        $membre = User::factory()->create(['password' => 'Ancien-Secret42!']);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->set('mot_de_passe_actuel', 'Ancien-Secret42!')
            ->set('nouveau_mot_de_passe', 'Nouveau-Secret42!')
            ->set('nouveau_mot_de_passe_confirmation', 'Nouveau-Secret42!')
            ->call('changerMotDePasse')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('Nouveau-Secret42!', $membre->fresh()->password));
    }

    public function test_changement_refuse_si_mot_de_passe_actuel_faux_ou_nouveau_faible(): void
    {
        $membre = User::factory()->create(['password' => 'Ancien-Secret42!']);

        Livewire::actingAs($membre)
            ->test(MonProfil::class)
            ->set('mot_de_passe_actuel', 'faux')
            ->set('nouveau_mot_de_passe', 'faible')
            ->set('nouveau_mot_de_passe_confirmation', 'faible')
            ->call('changerMotDePasse')
            ->assertHasErrors(['mot_de_passe_actuel', 'nouveau_mot_de_passe']);

        $this->assertTrue(Hash::check('Ancien-Secret42!', $membre->fresh()->password));
    }
}
