<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_voit_le_menu_du_site_sans_menu_lateral(): void
    {
        $this->get(route('accueil'))
            ->assertOk()
            ->assertSee('Connectez-vous ici')
            ->assertSee('Les groupes')
            ->assertSee('Contact')
            ->assertDontSee('id="menu-lateral"', false);
    }

    public function test_un_membre_voit_seulement_ses_fonctions(): void
    {
        $membre = User::factory()->avecRole(Role::Membre)->create(['prenom' => 'Julie']);

        $this->actingAs($membre)
            ->get(route('profil'))
            ->assertOk()
            ->assertSee('id="menu-lateral"', false)
            ->assertSee('Bonjour Julie')
            ->assertSee('Mon profil')
            ->assertDontSee(route('membres.index'))
            ->assertSee('Les groupes');
    }

    public function test_le_comite_voit_la_gestion_des_membres(): void
    {
        $comite = User::factory()->avecRole(Role::Comite)->create();

        $this->actingAs($comite)
            ->get(route('profil'))
            ->assertSee('Gestion')
            ->assertSee(route('membres.index'));
    }

    public function test_la_page_de_connexion_rouvre_le_menu_lateral(): void
    {
        $this->get(route('login'))->assertSee("localStorage.removeItem('menuLateralReplie')", false);
    }
}
