<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Membres\FicheMembre;
use App\Livewire\Site\OrdreComite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComiteTest extends TestCase
{
    use RefreshDatabase;

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    public function test_la_page_affiche_les_personnes_cochees_dans_l_ordre(): void
    {
        User::factory()->create(['prenom' => 'Marie', 'nom' => 'Présidente', 'comite_membre' => true, 'comite_fonction' => 'Présidente', 'comite_email' => 'presidente@gymechallens.ch', 'comite_ordre' => 1]);
        User::factory()->create(['prenom' => 'Paul', 'nom' => 'Caissier', 'comite_membre' => true, 'comite_fonction' => 'Caissier', 'comite_email' => null, 'email' => 'paul.caissier@gymechallens.ch', 'comite_ordre' => 2]);
        User::factory()->avecRole(Role::Comite)->create(['nom' => 'PasAffiche', 'comite_membre' => false]);

        $this->get(route('societe.comite'))
            ->assertOk()
            ->assertSeeInOrder(['Présidente', 'Marie Présidente', 'presidente@gymechallens.ch', 'Caissier', 'Paul Caissier', 'paul.caissier@gymechallens.ch'])
            ->assertDontSee('PasAffiche')
            ->assertDontSee('Modifier l’ordre');

        $this->get(route('accueil'))->assertSee(route('societe.comite'));
    }

    public function test_le_mobile_n_est_affiche_que_si_la_personne_l_a_choisi(): void
    {
        User::factory()->create(['comite_membre' => true, 'comite_fonction' => 'Secrétaire', 'mobile' => '+41 79 111 22 33', 'afficher_mobile' => false]);
        User::factory()->create(['comite_membre' => true, 'comite_fonction' => 'Vice-présidente', 'mobile' => '+41 78 444 55 66', 'afficher_mobile' => true]);

        $this->get(route('societe.comite'))
            ->assertDontSee('+41 79 111 22 33')
            ->assertSee('+41 78 444 55 66');
    }

    public function test_cocher_fait_partie_du_comite_dans_la_fiche(): void
    {
        User::factory()->create(['comite_membre' => true, 'comite_fonction' => 'Présidente', 'comite_ordre' => 1]);
        $membre = User::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('comite_membre', true)
            ->set('comite_fonction', 'Caissière')
            ->set('comite_identifiant_email', 'Caisse@autre.ch')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $membre->refresh();
        $this->assertTrue($membre->comite_membre);
        $this->assertSame('Caissière', $membre->comite_fonction);
        $this->assertSame('caisse@gymechallens.ch', $membre->comite_email);
        $this->assertSame(2, $membre->comite_ordre, 'Ajoutée à la fin du comité.');
    }

    public function test_la_fonction_est_obligatoire_si_la_case_est_cochee(): void
    {
        $membre = User::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('comite_membre', true)
            ->set('comite_fonction', '')
            ->call('enregistrer')
            ->assertHasErrors(['comite_fonction' => 'required_if']);
    }

    public function test_independant_du_niveau_d_autorisation(): void
    {
        // Transition : l'ancienne présidente n'a plus le niveau Comité mais reste affichée.
        User::factory()->avecRole(Role::Membre)->create(['nom' => 'Ancienne', 'comite_membre' => true, 'comite_fonction' => 'Présidente sortante']);

        $this->get(route('societe.comite'))->assertSee('Présidente sortante');
    }

    public function test_monter_et_descendre(): void
    {
        $a = User::factory()->create(['nom' => 'Alpha', 'comite_membre' => true, 'comite_fonction' => 'A', 'comite_ordre' => 1]);
        $b = User::factory()->create(['nom' => 'Beta', 'comite_membre' => true, 'comite_fonction' => 'B', 'comite_ordre' => 2]);

        $this->actingAs($this->comite())->get(route('societe.comite'))->assertSee('Modifier l’ordre');

        Livewire::actingAs($this->comite())
            ->test(OrdreComite::class)
            ->call('basculerEdition')
            ->assertSee('Monter')
            ->call('deplacer', $b->id, -1);

        $this->assertSame([$b->id, $a->id], User::duComite()->pluck('id')->all());
    }

    public function test_badge_comite_avec_infobulle_dans_la_liste_des_membres(): void
    {
        User::factory()->create(['nom' => 'Rochat', 'comite_membre' => true, 'comite_fonction' => 'Présidente', 'comite_email' => 'presidente@gymechallens.ch', 'comite_ordre' => 1]);
        User::factory()->create(['nom' => 'Dupont', 'comite_membre' => false]);

        $this->actingAs($this->comite())
            ->get(route('membres.index'))
            ->assertSee('★ Comité')
            ->assertSee('data-infobulle="Présidente · presidente@gymechallens.ch · position 1 sur la page du comité"', false);
    }

    public function test_un_moniteur_ne_peut_pas_changer_l_ordre(): void
    {
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->get(route('societe.comite'))
            ->assertDontSee('Modifier l’ordre');

        Livewire::actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->test(OrdreComite::class)
            ->call('basculerEdition')
            ->assertForbidden();
    }
}
