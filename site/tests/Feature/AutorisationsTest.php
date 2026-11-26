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

    public function test_un_membre_ou_un_moniteur_n_a_pas_acces(): void
    {
        foreach ([User::factory()->create(), User::factory()->avecRole(Role::Moniteur)->create()] as $user) {
            $this->actingAs($user)->get(route('membres.index'))->assertForbidden();
            $this->actingAs($user)->get(route('membres.create'))->assertForbidden();
        }
    }

    public function test_le_comite_gere_les_membres(): void
    {
        $comite = User::factory()->avecRole(Role::Comite)->create();
        $membre = User::factory()->create();

        $this->actingAs($comite)->get(route('membres.index'))->assertOk();
        $this->actingAs($comite)->get(route('membres.edit', $membre))->assertOk();
    }

    public function test_le_comite_ne_peut_pas_modifier_un_administrateur(): void
    {
        $comite = User::factory()->avecRole(Role::Comite)->create();
        $admin = User::factory()->avecRole(Role::Administrateur)->create();

        $this->actingAs($comite)->get(route('membres.edit', $admin))->assertForbidden();
    }

    public function test_le_comite_ne_peut_pas_donner_le_role_administrateur(): void
    {
        $comite = User::factory()->avecRole(Role::Comite)->create();
        $membre = User::factory()->create();

        Livewire::actingAs($comite)
            ->test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('role', 'administrateur')
            ->call('enregistrer')
            ->assertHasErrors('role');

        $this->assertFalse($membre->fresh()->estAdministrateur());
    }

    public function test_le_dernier_administrateur_ne_peut_pas_perdre_son_role(): void
    {
        $admin = User::factory()->avecRole(Role::Administrateur)->create();

        Livewire::actingAs($admin)
            ->test(FicheMembre::class, ['membre' => $admin])
            ->call('modifier')
            ->set('role', 'comite')
            ->call('enregistrer')
            ->assertHasErrors('role');

        $this->assertTrue($admin->fresh()->estAdministrateur());
    }

    public function test_un_administrateur_peut_nommer_un_autre_administrateur(): void
    {
        $admin = User::factory()->avecRole(Role::Administrateur)->create();
        $membre = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(FicheMembre::class, ['membre' => $membre])
            ->call('modifier')
            ->set('role', 'administrateur')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertTrue($membre->fresh()->estAdministrateur());
    }

    public function test_creer_un_membre_avec_e_mail_envoie_une_invitation(): void
    {
        Notification::fake();
        $comite = User::factory()->avecRole(Role::Comite)->create();

        Livewire::actingAs($comite)
            ->test(FicheMembre::class)
            ->set('civilite', 'monsieur')
            ->set('prenom', 'Nouveau')
            ->set('nom', 'Moniteur')
            ->set('mobile', '079 000 00 00')
            ->set('email_prive', 'nouveau@example.net')
            ->set('role', 'moniteur')
            ->set('identifiant_email', 'nouveau')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $nouveau = User::firstWhere('email', 'nouveau@gymechallens.ch');

        Notification::assertSentTo($nouveau, LienMotDePasse::class, fn (LienMotDePasse $n) => $n->invitation);
    }

    public function test_un_lien_mot_de_passe_oublie_ne_vaut_pas_invitation(): void
    {
        $membre = User::factory()->create(['email' => 'julie@example.org']);
        $tokenReinitialisation = Password::broker('users')->createToken($membre);

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => $tokenReinitialisation, 'invitation' => true])
            ->set('password', 'Nouveau-Secret42!')
            ->set('password_confirmation', 'Nouveau-Secret42!')
            ->call('enregistrer')
            ->assertHasErrors('password');
    }
}
