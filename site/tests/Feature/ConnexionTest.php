<?php

namespace Tests\Feature;

use App\Livewire\Auth\Connexion;
use App\Livewire\Auth\MotDePasseOublie;
use App\Livewire\Auth\NouveauMotDePasse;
use App\Livewire\Auth\SaisieCode;
use App\Models\User;
use App\Notifications\CodeConnexion;
use App\Notifications\LienMotDePasse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    private function membre(array $attributs = []): User
    {
        return User::factory()->create([
            'email' => 'julie@example.org',
            'password' => 'MotDePasse123',
            ...$attributs,
        ]);
    }

    /**
     * Étape 1 réussie : renvoie le code envoyé par e-mail.
     */
    private function etapeMotDePasse(): string
    {
        Livewire::test(Connexion::class)
            ->set('email', 'Julie@Example.org')
            ->set('password', 'MotDePasse123')
            ->call('seConnecter')
            ->assertHasNoErrors()
            ->assertRedirect(route('connexion.code'));

        $code = null;
        Notification::assertSentTo(User::firstWhere('email', 'julie@example.org'), CodeConnexion::class,
            function (CodeConnexion $notification) use (&$code) {
                $code = $notification->code;

                return true;
            });

        return $code;
    }

    public function test_les_pages_de_connexion_s_affichent(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Connexion');
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'abc']))->assertOk();
        $this->get(route('bienvenue', ['token' => 'abc']))->assertOk()->assertSee('Bienvenue');
    }

    public function test_connexion_complete_avec_le_code_recu_par_e_mail(): void
    {
        Notification::fake();
        $membre = $this->membre();

        $code = $this->etapeMotDePasse();

        $this->assertGuest();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $membre->fresh()->code_connexion, 'Le code est stocké haché.');

        Livewire::test(SaisieCode::class)
            ->set('code', $code)
            ->call('verifier')
            ->assertHasNoErrors()
            ->assertRedirect(route('profil'));

        $this->assertAuthenticatedAs($membre);
        $this->assertNull($membre->fresh()->code_connexion, 'Le code ne peut servir qu\'une fois.');
        $this->assertNotNull($membre->fresh()->derniere_connexion_le);
    }

    public function test_mauvais_mot_de_passe(): void
    {
        Notification::fake();
        $this->membre();

        Livewire::test(Connexion::class)
            ->set('email', 'julie@example.org')
            ->set('password', 'faux')
            ->call('seConnecter')
            ->assertHasErrors('email');

        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_un_compte_inconnu_donne_le_meme_message(): void
    {
        Livewire::test(Connexion::class)
            ->set('email', 'personne@example.org')
            ->set('password', 'MotDePasse123')
            ->call('seConnecter')
            ->assertSee('E-mail ou mot de passe incorrect.');
    }

    public function test_un_ancien_membre_ne_peut_pas_se_connecter(): void
    {
        Notification::fake();
        $this->membre(['date_sortie' => now()->subDay()]);

        Livewire::test(Connexion::class)
            ->set('email', 'julie@example.org')
            ->set('password', 'MotDePasse123')
            ->call('seConnecter')
            ->assertHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_blocage_apres_trop_de_tentatives(): void
    {
        $this->membre();

        for ($i = 0; $i < Connexion::TENTATIVES_MAX; $i++) {
            Livewire::test(Connexion::class)
                ->set('email', 'julie@example.org')
                ->set('password', 'faux')
                ->call('seConnecter');
        }

        Livewire::test(Connexion::class)
            ->set('email', 'julie@example.org')
            ->set('password', 'MotDePasse123')
            ->call('seConnecter')
            ->assertSee('Trop de tentatives');
    }

    public function test_mauvais_code_puis_blocage_du_code(): void
    {
        Notification::fake();
        $membre = $this->membre();
        $code = $this->etapeMotDePasse();
        $faux = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < config('auth.code_connexion.tentatives_max'); $i++) {
            Livewire::test(SaisieCode::class)->set('code', $faux)->call('verifier')->assertHasErrors('code');
        }

        // Même le bon code ne fonctionne plus après trop d'essais.
        Livewire::test(SaisieCode::class)
            ->set('code', $code)
            ->call('verifier')
            ->assertSee('Demandez un nouveau code');

        $this->assertGuest();
    }

    public function test_code_expire(): void
    {
        Notification::fake();
        $this->membre();
        $code = $this->etapeMotDePasse();

        $this->travel(11)->minutes();

        Livewire::test(SaisieCode::class)->set('code', $code)->call('verifier')->assertHasErrors('code');
        $this->assertGuest();
    }

    public function test_la_page_du_code_exige_l_etape_1(): void
    {
        $this->get(route('connexion.code'))->assertRedirect(route('login'));
    }

    public function test_deconnexion(): void
    {
        $this->actingAs($this->membre())
            ->post(route('logout'))
            ->assertRedirect(route('accueil'));

        $this->assertGuest();
    }

    public function test_mot_de_passe_oublie_et_reinitialisation(): void
    {
        Notification::fake();
        $membre = $this->membre();

        Livewire::test(MotDePasseOublie::class)
            ->set('email', 'julie@example.org')
            ->call('envoyerLien')
            ->assertSet('envoye', true);

        $token = null;
        Notification::assertSentTo($membre, LienMotDePasse::class, function (LienMotDePasse $notification) use (&$token) {
            $token = $notification->token;

            return ! $notification->invitation;
        });

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => $token])
            ->assertSet('email', 'julie@example.org')
            ->set('password', 'Nouveau-Secret42!')
            ->set('password_confirmation', 'Nouveau-Secret42!')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Nouveau-Secret42!', $membre->fresh()->password));
    }

    public function test_mot_de_passe_oublie_ne_revele_pas_si_le_compte_existe(): void
    {
        Notification::fake();

        Livewire::test(MotDePasseOublie::class)
            ->set('email', 'inconnu@example.org')
            ->call('envoyerLien')
            ->assertSet('envoye', true);

        Notification::assertNothingSent();
    }

    public function test_un_lien_invalide_est_refuse(): void
    {
        $membre = $this->membre();

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => 'faux-jeton'])
            ->set('password', 'Nouveau-Secret42!')
            ->set('password_confirmation', 'Nouveau-Secret42!')
            ->call('enregistrer')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('MotDePasse123', $membre->fresh()->password));
    }

    public function test_l_e_mail_du_lien_ne_peut_pas_etre_modifie(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => 'abc'])
            ->set('email', 'pirate@example.org');
    }

    public function test_un_lien_sans_e_mail_est_refuse(): void
    {
        Livewire::test(NouveauMotDePasse::class, ['token' => 'abc'])
            ->set('password', 'Nouveau-Secret42!')
            ->set('password_confirmation', 'Nouveau-Secret42!')
            ->call('enregistrer')
            ->assertHasErrors('password')
            ->assertSee('Ce lien est incomplet');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function motsDePasseFaibles(): array
    {
        return [
            'trop court' => ['Court-42!'],
            'sans majuscule' => ['sans-majuscule-42!'],
            'sans minuscule' => ['SANS-MINUSCULE-42!'],
            'sans chiffre' => ['Sans-Chiffre-Ici!'],
            'sans caractère spécial' => ['SansCaractere42'],
            'trop long' => ['Aa1!'.str_repeat('x', 61)],
        ];
    }

    #[DataProvider('motsDePasseFaibles')]
    public function test_mot_de_passe_faible_refuse(string $motDePasse): void
    {
        $membre = $this->membre();
        $token = Password::broker('users')->createToken($membre);

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => $token])
            ->set('password', $motDePasse)
            ->set('password_confirmation', $motDePasse)
            ->call('enregistrer')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('MotDePasse123', $membre->fresh()->password));
    }

    public function test_un_espace_compte_comme_caractere_special(): void
    {
        $membre = $this->membre();
        $token = Password::broker('users')->createToken($membre);

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => $token])
            ->set('password', 'Une phrase de 2026')
            ->set('password_confirmation', 'Une phrase de 2026')
            ->call('enregistrer')
            ->assertHasNoErrors();
    }

    public function test_invitation_d_un_nouveau_membre(): void
    {
        Notification::fake();
        $membre = $this->membre(['password' => null]);

        $membre->envoyerInvitation();

        $token = null;
        Notification::assertSentTo($membre, LienMotDePasse::class, function (LienMotDePasse $notification) use (&$token) {
            $token = $notification->token;

            return $notification->invitation;
        });

        // Le lien d'invitation est encore valable après 3 jours.
        $this->travel(3)->days();

        $this->get(route('bienvenue', ['token' => $token, 'email' => 'julie@example.org']))
            ->assertOk()
            ->assertSee('Bienvenue');

        Livewire::withQueryParams(['email' => 'julie@example.org'])
            ->test(NouveauMotDePasse::class, ['token' => $token, 'invitation' => true])
            ->set('password', 'Premier-Secret42!')
            ->set('password_confirmation', 'Premier-Secret42!')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Premier-Secret42!', $membre->fresh()->password));
    }

    public function test_un_lien_d_invitation_ne_sert_qu_une_fois(): void
    {
        $membre = $this->membre(['password' => null]);
        $token = Password::broker('invitations')->createToken($membre);

        foreach (['Premier-Secret42!' => false, 'Deuxieme-Secret42!' => true] as $motDePasse => $erreurAttendue) {
            $test = Livewire::withQueryParams(['email' => 'julie@example.org'])
                ->test(NouveauMotDePasse::class, ['token' => $token, 'invitation' => true])
                ->set('password', $motDePasse)
                ->set('password_confirmation', $motDePasse)
                ->call('enregistrer');

            $erreurAttendue ? $test->assertHasErrors('password') : $test->assertHasNoErrors();
        }

        $this->assertTrue(Hash::check('Premier-Secret42!', $membre->fresh()->password));
    }
}
