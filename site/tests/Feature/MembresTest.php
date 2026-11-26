<?php

namespace Tests\Feature;

use App\Enums\Civilite;
use App\Livewire\Membres\FicheMembre;
use App\Livewire\Membres\ListeMembres;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MembresTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creation_d_un_membre(): void
    {
        $this->get(route('membres.create'))->assertOk();

        Livewire::test(FicheMembre::class)
            ->set('civilite', 'madame')
            ->set('prenom', 'Julie')
            ->set('nom', 'Exemple')
            ->set('npa', '1040')
            ->set('localite', 'Echallens')
            ->set('mobile', '079 123 45 67')
            ->set('email', 'Julie.Exemple@Example.org')
            ->set('avs', '7561234567897')
            ->set('date_naissance', '1990-05-12')
            ->set('afficher_mobile', true)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('membres.index'));

        $membre = User::where('nom', 'Exemple')->firstOrFail();

        $this->assertSame(Civilite::Madame, $membre->civilite);
        $this->assertSame('julie.exemple@example.org', $membre->email);
        $this->assertSame('756.1234.5678.97', $membre->avs);
        $this->assertTrue($membre->afficher_mobile);
        $this->assertNull($membre->password);
        $this->assertNull($membre->rue, 'Les champs vides sont enregistrés comme NULL.');
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

        Livewire::test(FicheMembre::class)
            ->set('prenom', '')
            ->set('nom', 'Test')
            ->set('npa', '12')
            ->set('email', 'pris@example.org')
            ->set('avs', '756.1234.5678.98')
            ->set('date_entree', '2025-01-01')
            ->set('date_sortie', '2024-01-01')
            ->call('enregistrer')
            ->assertHasErrors(['prenom', 'npa', 'email', 'avs', 'date_sortie']);
    }

    public function test_modification_d_un_membre(): void
    {
        $membre = User::factory()->create(['localite' => 'Bercher']);

        $this->get(route('membres.edit', $membre))
            ->assertOk()
            ->assertSee('Bercher');

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->assertSet('localite', 'Bercher')
            ->set('localite', 'Echallens')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Echallens', $membre->fresh()->localite);
    }

    public function test_un_membre_peut_garder_son_propre_e_mail(): void
    {
        $membre = User::factory()->create(['email' => 'moi@example.org']);

        Livewire::test(FicheMembre::class, ['membre' => $membre])
            ->set('nom', 'Nouveau nom')
            ->call('enregistrer')
            ->assertHasNoErrors();
    }
}
