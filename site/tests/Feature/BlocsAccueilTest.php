<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\BlocTexte;
use App\Models\BlocContenu;
use App\Models\JournalModification;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlocsAccueilTest extends TestCase
{
    use RefreshDatabase;

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    private function bloc(string $cle): BlocContenu
    {
        return BlocContenu::where('cle', $cle)->firstOrFail();
    }

    public function test_les_textes_d_origine_sont_repris_et_l_image_nouveau_retiree(): void
    {
        $this->get(route('accueil'))
            ->assertOk()
            ->assertSee('anciennement GYM de la Bruyère')
            ->assertSee('<strong>3 cours d’essai gratuits</strong>', false)
            ->assertSee('La FSG Echallens recrute !')
            ->assertDontSee('animation-accueil.gif')
            ->assertDontSee('✎ Modifier');
    }

    public function test_les_boutons_de_modification_sont_reserves_au_comite(): void
    {
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->get(route('accueil'))->assertDontSee('✎ Modifier');

        $this->actingAs($this->comite())
            ->get(route('accueil'))->assertSee('✎ Modifier');
    }

    public function test_modifier_le_texte_avec_mise_en_forme_nettoyee(): void
    {
        Livewire::actingAs($this->comite())
            ->test(BlocTexte::class, ['cle' => 'accueil.encart', 'presentation' => 'encart'])
            ->call('modifier')
            ->assertSet('enEdition', true)
            ->set('contenu', '<p><b>Nouveau</b> texte <span style="color: rgb(93, 151, 51)">vert</span><script>alert(1)</script><img src=x onerror="alert(1)"></p>')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('enEdition', false);

        $this->assertSame(
            '<p><strong>Nouveau</strong> texte <span style="color: #5d9733">vert</span></p>',
            $this->bloc('accueil.encart')->contenu,
        );

        $this->get(route('accueil'))->assertSee('<strong>Nouveau</strong> texte', false)->assertDontSee('onerror');
    }

    public function test_texte_vide_refuse(): void
    {
        Livewire::actingAs($this->comite())
            ->test(BlocTexte::class, ['cle' => 'accueil.texte'])
            ->call('modifier')
            ->set('contenu', '<p><br></p>')
            ->call('enregistrer')
            ->assertHasErrors('contenu');
    }

    public function test_recrute_titre_modifiable_et_masquable(): void
    {
        Livewire::actingAs($this->comite())
            ->test(BlocTexte::class, ['cle' => 'accueil.recrute', 'presentation' => 'recrute'])
            ->call('modifier')
            ->set('titre', 'On cherche des moniteurs !')
            ->set('visible', false)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $bloc = $this->bloc('accueil.recrute');
        $this->assertSame('On cherche des moniteurs !', $bloc->titre);
        $this->assertFalse($bloc->visible);

        auth()->logout();
        $this->get(route('accueil'))->assertDontSee('On cherche des moniteurs !')->assertSee('accueil--une-colonne', false);

        $this->actingAs($this->comite())->get(route('accueil'))
            ->assertSee('On cherche des moniteurs !')
            ->assertSee('Masqué pour les visiteurs');
    }

    public function test_afficher_de_nouveau_le_bloc_masque(): void
    {
        $this->bloc('accueil.recrute')->update(['visible' => false]);

        Livewire::actingAs($this->comite())
            ->test(BlocTexte::class, ['cle' => 'accueil.recrute', 'presentation' => 'recrute'])
            ->call('basculerVisibilite');

        $this->assertTrue($this->bloc('accueil.recrute')->visible);
    }

    public function test_les_autres_blocs_ne_peuvent_pas_etre_masques(): void
    {
        Livewire::actingAs($this->comite())
            ->test(BlocTexte::class, ['cle' => 'accueil.texte'])
            ->call('basculerVisibilite');

        $this->assertTrue($this->bloc('accueil.texte')->visible);
    }

    public function test_un_moniteur_ne_peut_pas_modifier(): void
    {
        Livewire::actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->test(BlocTexte::class, ['cle' => 'accueil.texte'])
            ->call('modifier')
            ->assertForbidden();
    }

    public function test_modification_inscrite_au_journal_et_restaurable(): void
    {
        $avant = $this->bloc('accueil.encart')->contenu;

        Livewire::actingAs($this->comite())
            ->test(BlocTexte::class, ['cle' => 'accueil.encart', 'presentation' => 'encart'])
            ->call('modifier')
            ->set('contenu', '<p>Changé</p>')
            ->call('enregistrer');

        $entree = JournalModification::latest('id')->firstOrFail();
        $this->assertSame('Accueil – encadré gris', $entree->libelle);

        app(Journal::class)->restaurer($entree);

        $this->assertSame($avant, $this->bloc('accueil.encart')->contenu);
    }
}
