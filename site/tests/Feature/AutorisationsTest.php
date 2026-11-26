<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Auth\NouveauMotDePasse;
use App\Livewire\Membres\FicheMembre;
use App\Models\User;
use App\Notifications\LienMotDePasse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class AutorisationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $membre = User::factory()->create();

        $this->get(route('membres.index'))->assertRedirect(route('login'));
        $this->get(route('membres.create'))->assertRedirect(route('login'));
        $this->get(route('membres.edit', $membre))->assertRedirect(route('login'));
    }

    public function test_un_membre_sans_role_ou_moniteur_n_a_pas_acces(): void
    {
        foreach ([User::factory()->create(), User::factory()->avecRoles(Role::Moniteur)->create()] as $user) {
            $this->actingAs($user)->get(route('membres.index'))->assertForbidden();
            $this->actingAs($user)->get(route('membres.create'))->assertForbidden();
        }
    }

    public function test_le_comite_gere_les_membres(): void
    {
        $comite = User::factory()->avecRoles(Role::Comite)->create();
        $membre = User::factory()->create();

        $this->actingAs($comite)->get(route('membres.index'))->assertOk();
        $this->actingAs($comite)->get(route('membres.edit', $membre))->assertOk();
    }

    public function test_le_comite_ne_peut_pas_modifier_un_administrateur(): void
    {
        $comite = User::factory()->avecRoles(Role::Comite)->create();
        $admin = User::factory()->avecRoles(Role::Administrateur)->create();

        $this->actingAs($comite)->get(route('membres.edit', $admin))->assertForbidden();
    }

    public function test_le_comite_ne_peut_pas_donner_le_role_administrateur(): void
    {
        $comite = User::factory()->avecRoles(Role::Comite)->create();
        $membre = User::factory()->create();

        Livewire::actingAs($comite)
            ->test(FicheMembre::class, ['membre' => $membre])
            ->set('roles', ['administrateur'])
            ->call('enregistrer')
            ->assertHasErrors('roles');

        $this->assertFalse($membre->fresh()->estAdministrateur());
    }

    public function test_le_dernier_administrateur_ne_peut_pas_perdre_son_role(): void
    {
        $admin = User::factory()->avecRoles(Role::Administrateur)->create();

        Livewire::actingAs($admin)
            ->test(FicheMembre::class, ['membre' => $admin])
            ->set('roles', ['comite'])
            ->call('enregistrer')
            ->assertHasErrors('roles');

        $this->assertTrue($admin->fresh()->estAdministrateur());
    }

    public function test_un_administrateur_peut_nommer_un_autre_administrateur(): void
    {
        $admin = User::factory()->avecRoles(Role::Administrateur)->create();
        $membre = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(FicheMembre::class, ['membre' => $membre])
            ->set('roles', ['administrateur'])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertTrue($membre->fresh()->estAdministrateur());
    }

    public function test_creer_un_membre_avec_e_mail_envoie_une_invitation(): void
    {
        Notification::fake();
        $comite = User::factory()->avecRoles(Role::Comite)->create();

        Livewire::actingAs($comite)
            ->test(FicheMembre::class)
            ->set('prenom', 'Nouveau')
            ->set('nom', 'Moniteur')
            ->set('email', 'nouveau@example.org')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $nouveau = User::firstWhere('email', 'nouveau@example.org');

        Notification::assertSentTo($nouveau, LienMotDePasse::class, fn (LienMotDePasse $n) => $n->invitation);
    }

    public function test_un_lien_mot_de_passe_oublie_ne_vaut_pas_invitation(): void
    {
        $membre = User::factory()->create(['email' => 'julie@example.org']);
        $tokenReinitialisation = Password::broker('users')->createToken($membre);

        Livewire::test(NouveauMotDePasse::class, ['token' => $tokenReinitialisation, 'invitation' => true])
            ->set('email', 'julie@example.org')
            ->set('password', 'NouveauSecret42')
            ->set('password_confirmation', 'NouveauSecret42')
            ->call('enregistrer')
            ->assertHasErrors('email');
    }
}
