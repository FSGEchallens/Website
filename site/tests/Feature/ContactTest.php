<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\FormulaireContact;
use App\Mail\MessageContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Formulaire rempli correctement, après le délai minimal.
     */
    private function formulaireRempli(): Testable
    {
        $formulaire = Livewire::test(FormulaireContact::class)
            ->set('prenom', 'Julie')
            ->set('nom', 'Exemple')
            ->set('email', 'julie@example.net')
            ->set('sujet', 'Cours d’essai')
            ->set('message', 'Bonjour, mon fils aimerait essayer l’agrès. Merci !');

        $this->travel(5)->seconds();

        return $formulaire->set('reponse', (string) session('contact.reponse'));
    }

    public function test_la_page_affiche_les_informations_et_le_formulaire(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Informations')
            ->assertSee('Case postale 304')
            ->assertSee('CH25 8080 8008 3074 6687 8')
            ->assertSee('Formulaire de contact')
            ->assertSee('Question anti-robot : Combien font')
            ->assertDontSee('✎ Modifier');

        $this->get(route('accueil'))->assertSee(route('contact'));
    }

    public function test_la_reponse_n_est_pas_dans_la_page(): void
    {
        $page = $this->get(route('contact'));
        $reponse = session('contact.reponse');

        $this->assertIsInt($reponse);
        $page->assertDontSee('"reponse":'.$reponse, false);
    }

    public function test_envoi_du_message_a_contact(): void
    {
        Mail::fake();

        $this->formulaireRempli()
            ->call('envoyer')
            ->assertHasNoErrors()
            ->assertSet('envoye', true)
            ->assertSee('Votre message a bien été envoyé');

        Mail::assertSent(MessageContact::class, function (MessageContact $mail) {
            return $mail->hasTo('contact@gymechallens.ch')
                && $mail->hasReplyTo('julie@example.net')
                && $mail->hasSubject('[Site] Cours d’essai');
        });
    }

    public function test_le_contenu_de_l_e_mail_reste_lisible(): void
    {
        $mail = new MessageContact([
            'prenom' => 'Julie', 'nom' => 'L\'Exemple', 'email' => 'julie@example.net',
            'sujet' => null, 'message' => "Ligne 1\nC'est <b>moi</b>",
        ]);

        $mail->assertSeeInText("Julie L'Exemple");
        $mail->assertSeeInText("C'est <b>moi</b>");
        $mail->assertSeeInText('(sans sujet)');
    }

    public function test_la_reponse_en_lettres_est_acceptee(): void
    {
        Mail::fake();
        $noms = [2 => 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit'];

        $formulaire = $this->formulaireRempli();
        $formulaire->set('reponse', ucfirst($noms[session('contact.reponse')]).'.')
            ->call('envoyer')
            ->assertHasNoErrors();

        Mail::assertSentCount(1);
    }

    public function test_mauvaise_reponse_refusee_et_nouvelle_question(): void
    {
        Mail::fake();

        $formulaire = $this->formulaireRempli();
        $formulaire->set('reponse', '999')
            ->call('envoyer')
            ->assertHasErrors('reponse')
            ->assertSet('reponse', '');

        Mail::assertNothingSent();
    }

    public function test_le_champ_piege_bloque_les_robots_en_silence(): void
    {
        Mail::fake();

        $this->formulaireRempli()
            ->set('site_web', 'http://spam.example')
            ->call('envoyer')
            ->assertSet('envoye', true);

        Mail::assertNothingSent();
    }

    public function test_envoi_trop_rapide_refuse(): void
    {
        Mail::fake();

        Livewire::test(FormulaireContact::class)
            ->set('prenom', 'Robot')
            ->set('nom', 'Rapide')
            ->set('email', 'robot@example.net')
            ->set('message', 'Message envoyé immédiatement.')
            ->set('reponse', (string) session('contact.reponse'))
            ->call('envoyer')
            ->assertHasErrors('message');

        Mail::assertNothingSent();
    }

    public function test_trop_de_liens_refuse(): void
    {
        Mail::fake();

        $this->formulaireRempli()
            ->set('message', 'Achetez https://a.example https://b.example https://c.example maintenant')
            ->call('envoyer')
            ->assertHasErrors('message');

        Mail::assertNothingSent();
    }

    public function test_limite_d_envois(): void
    {
        Mail::fake();

        for ($i = 0; $i < FormulaireContact::ENVOIS_MAX; $i++) {
            $this->formulaireRempli()->call('envoyer')->assertHasNoErrors();
        }

        $this->formulaireRempli()->call('envoyer')->assertHasErrors('message');

        Mail::assertSentCount(FormulaireContact::ENVOIS_MAX);
    }

    public function test_validation(): void
    {
        Livewire::test(FormulaireContact::class)
            ->set('prenom', "Julie\nBcc: pirate@example.net")
            ->set('email', 'pas-un-email')
            ->set('message', 'court')
            ->call('envoyer')
            ->assertHasErrors(['prenom', 'nom', 'email', 'message', 'reponse']);
    }

    public function test_le_comite_peut_modifier_les_informations(): void
    {
        $this->actingAs(User::factory()->avecRole(Role::Comite)->create())
            ->get(route('contact'))
            ->assertSee('✎ Modifier');
    }
}
