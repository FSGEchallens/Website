<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccueilTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_d_accueil_s_affiche_pour_les_visiteurs(): void
    {
        $this->get(route('accueil'))
            ->assertOk()
            ->assertSee('Bienvenue sur le site de la FSG Echallens')
            ->assertSee('3 cours d’essai gratuits')
            ->assertSee('La FSG Echallens recrute !')
            ->assertSee('Nos partenaires')
            ->assertSee('images/partenaires/FSG_logo.jpg', false)
            ->assertSee('© FSG Echallens');
    }

    public function test_la_page_d_accueil_s_affiche_aussi_une_fois_connecte(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('accueil'))
            ->assertOk()
            ->assertSee('Bienvenue sur le site de la FSG Echallens')
            ->assertSee('id="menu-lateral"', false);
    }
}
