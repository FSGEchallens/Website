<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\BlocTexte;
use App\Models\BlocContenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoriqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_historique_s_affiche(): void
    {
        $this->get(route('societe.historique'))
            ->assertOk()
            ->assertSee('Historique')
            ->assertSee('6 avril 1921')
            ->assertSee('LA BRUYÈRE Echallens')
            ->assertSee('images/societe/Gym-Bruyere_logo.png', false)
            ->assertDontSee('✎ Modifier');
    }

    public function test_le_menu_pointe_vers_la_nouvelle_page_et_l_agenda_a_disparu(): void
    {
        $this->get(route('accueil'))
            ->assertSee(route('societe.historique'))
            ->assertDontSee('gymechallens.ch/historique/')
            ->assertDontSee('Agenda');
    }

    public function test_le_comite_peut_modifier_l_historique(): void
    {
        $comite = User::factory()->avecRole(Role::Comite)->create();

        $this->actingAs($comite)->get(route('societe.historique'))->assertSee('✎ Modifier');

        Livewire::actingAs($comite)
            ->test(BlocTexte::class, ['cle' => 'societe.historique'])
            ->call('modifier')
            ->set('contenu', '<ul><li><strong>2026</strong> : 105 ans de la société.</li></ul>')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertStringContainsString('105 ans', BlocContenu::where('cle', 'societe.historique')->value('contenu'));
        $this->get(route('societe.historique'))->assertSee('105 ans de la société');
    }

    public function test_un_moniteur_ne_voit_pas_le_bouton(): void
    {
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->get(route('societe.historique'))
            ->assertDontSee('✎ Modifier');
    }
}
