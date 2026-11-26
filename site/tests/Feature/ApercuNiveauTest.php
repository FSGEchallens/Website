<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApercuNiveauTest extends TestCase
{
    use RefreshDatabase;

    private function webmaster(): User
    {
        return User::factory()->avecRole(Role::Administrateur)->create(['email' => 'webmaster@gymechallens.ch']);
    }

    public function test_le_webmaster_voit_la_liste_des_niveaux(): void
    {
        $this->actingAs($this->webmaster())
            ->get(route('profil'))
            ->assertSee('Voir le site comme')
            ->assertSee('(mon niveau)');
    }

    public function test_un_autre_administrateur_n_a_pas_l_option(): void
    {
        $admin = User::factory()->avecRole(Role::Administrateur)->create(['email' => 'autre@gymechallens.ch']);

        $this->actingAs($admin)->get(route('profil'))->assertDontSee('Voir le site comme');
        $this->actingAs($admin)->post(route('apercu-niveau'), ['niveau' => 'membre'])->assertForbidden();
    }

    public function test_apercu_en_tant_que_membre_puis_retour(): void
    {
        $webmaster = $this->webmaster();
        $this->actingAs($webmaster);

        $this->from(route('profil'))->post(route('apercu-niveau'), ['niveau' => 'membre'])->assertRedirect(route('profil'));

        $this->get(route('profil'))
            ->assertSee('Aperçu du site en tant que')
            ->assertSee('Revenir à Administrateur')
            ->assertDontSee(route('membres.index'));
        $this->get(route('membres.index'))->assertForbidden()->assertSee('Accès refusé');
        $this->get(route('journal.index'))->assertForbidden();

        $this->post(route('apercu-niveau'), ['niveau' => 'administrateur']);

        $this->get(route('membres.index'))->assertOk();
        $this->get(route('profil'))->assertDontSee('Aperçu du site en tant que');
    }

    public function test_apercu_en_tant_que_comite(): void
    {
        $this->actingAs($this->webmaster());
        $this->post(route('apercu-niveau'), ['niveau' => 'comite']);

        $this->get(route('membres.index'))->assertOk();
        $this->get(route('journal.index'))->assertForbidden();
        $this->get(route('accueil'))->assertSee('✎ Modifier');
    }

    public function test_le_niveau_reel_n_est_pas_modifie(): void
    {
        $webmaster = $this->webmaster();
        $this->actingAs($webmaster)->post(route('apercu-niveau'), ['niveau' => 'membre']);

        $this->assertSame(Role::Administrateur, $webmaster->fresh()->role);
    }

    public function test_l_apercu_ne_concerne_que_le_webmaster_lui_meme(): void
    {
        $webmaster = $this->webmaster();
        $this->actingAs($webmaster)->post(route('apercu-niveau'), ['niveau' => 'membre']);

        $autreAdmin = User::factory()->avecRole(Role::Administrateur)->create();

        $this->assertTrue($autreAdmin->estAdministrateur(), 'Les autres comptes gardent leur niveau.');
        $this->assertFalse($webmaster->estAdministrateur());
    }

    public function test_niveau_inconnu_refuse_et_l_apercu_s_arrete_a_la_deconnexion(): void
    {
        $this->actingAs($this->webmaster());

        $this->post(route('apercu-niveau'), ['niveau' => 'super-admin'])->assertStatus(422);

        $this->post(route('apercu-niveau'), ['niveau' => 'membre']);
        $this->post(route('logout'));

        $this->assertNull(session('apercu_niveau'));
    }

    public function test_pas_d_option_si_le_compte_webmaster_n_est_pas_administrateur(): void
    {
        $webmaster = User::factory()->avecRole(Role::Comite)->create(['email' => 'webmaster@gymechallens.ch']);

        $this->actingAs($webmaster)->post(route('apercu-niveau'), ['niveau' => 'membre'])->assertForbidden();
    }
}
