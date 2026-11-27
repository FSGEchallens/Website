<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Cours\GestionCours;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\JournalModification;
use App\Models\Salle;
use App\Models\SessionCours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GestionCoursTest extends TestCase
{
    use RefreshDatabase;

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    public function test_la_page_est_reservee_au_comite_et_a_l_administrateur(): void
    {
        $this->get(route('cours.gestion'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())->get(route('cours.gestion'))->assertForbidden();
        $this->actingAs($this->comite())->get(route('cours.gestion'))->assertOk()->assertSee('<h1>Groupes</h1>', false);
    }

    public function test_l_arbre_affiche_groupes_cours_et_sessions(): void
    {
        $moniteur = User::factory()->avecRole(Role::Moniteur)->create(['prenom' => 'Léa', 'nom' => 'Exemple']);
        $groupe = Groupe::factory()->create(['nom' => 'Agrès test', 'prix' => 300]);
        $cours = Cours::factory()->for($groupe)->create(['jour' => null, 'heure_debut' => null, 'heure_fin' => null, 'moniteurs' => [$moniteur->id]]);
        SessionCours::factory()->for($cours)->create(['jour' => 2, 'heure_debut' => '18:00', 'heure_fin' => '20:00']);
        SessionCours::factory()->for($cours)->create(['jour' => 4, 'heure_debut' => '18:00', 'heure_fin' => '20:00', 'salles' => [Salle::factory()->create(['nom' => 'Salle B'])->id]]);

        Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->assertSee('Agrès test')
            ->assertSee('CHF 300')
            ->assertSee('2 sessions par semaine')
            ->assertSee('Mardi 18:00–20:00')
            ->assertSee('Jeudi 18:00–20:00')
            ->assertSee('Léa Exemple (comme le cours)')
            ->assertSee('Salle B');
    }

    public function test_creer_un_groupe_puis_un_cours_avec_moniteurs(): void
    {
        $principal = User::factory()->avecRole(Role::Moniteur)->create();
        $second = User::factory()->avecRole(Role::Comite)->create();

        $composant = Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->call('nouveauGroupe')
            ->set('donnees.nom', 'Gym-danse plaisir')
            ->set('donnees.description', '<p>Texte <script>alert(1)</script></p>')
            ->set('donnees.prix', '220')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $groupe = Groupe::firstOrFail();
        $this->assertSame('gym-danse-plaisir', $groupe->slug);
        $this->assertStringNotContainsString('script', (string) $groupe->description);
        $this->assertSame('220.00', $groupe->prix);

        $composant->call('nouveauCours', $groupe->id)
            ->set('donnees.jour', '1')
            ->set('donnees.heure_debut', '17:30')
            ->set('donnees.heure_fin', '18:30')
            ->set('donnees.salle', 'Echallens, Court-Champ 4')
            ->set('moniteurAAjouter', $second->id)->call('ajouterMoniteur')
            ->set('moniteurAAjouter', $principal->id)->call('ajouterMoniteur')
            ->call('rendrePrincipal', 1)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $cours = Cours::firstOrFail();
        $this->assertSame([$principal->id, $second->id], $cours->moniteurs);
        $this->assertSame('Lundi 17:30–18:30', $cours->libelleHoraire());
        $this->assertTrue(JournalModification::where('sujet_type', Cours::class)->exists());
    }

    public function test_tous_les_membres_sont_proposes_et_la_salle_se_choisit_dans_la_liste(): void
    {
        $membre = User::factory()->avecRole(Role::Membre)->create(['prenom' => 'Zoé', 'nom' => 'Simplemembre']);
        $salle = Salle::factory()->create(['nom' => 'Echallens, Court-Champ 3']);
        $salle4 = Salle::factory()->create(['nom' => 'Echallens, Court-Champ 4']);
        $groupe = Groupe::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->call('nouveauCours', $groupe->id)
            ->assertSee('Simplemembre Zoé')
            ->assertSee('Echallens, Court-Champ 4')
            ->set('moniteurAAjouter', $membre->id)->call('ajouterMoniteur')
            ->set('salleAAjouter', $salle->id)->call('ajouterSalle')
            ->set('salleAAjouter', $salle4->id)->call('ajouterSalle')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $cours = Cours::firstOrFail();
        $this->assertSame([$membre->id], $cours->moniteurs);
        $this->assertSame([$salle->id, $salle4->id], $cours->salles);

        Livewire::actingAs($this->comite())->test(GestionCours::class)->assertSee('Echallens, Court-Champ 3 + Echallens, Court-Champ 4');
    }

    public function test_un_moniteur_ou_une_salle_inconnus_sont_refuses(): void
    {
        $groupe = Groupe::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->call('nouveauCours', $groupe->id)
            ->set('donnees.moniteurs', [999999])
            ->set('donnees.salles', [999999])
            ->call('enregistrer')
            ->assertHasErrors(['donnees.moniteurs.0', 'donnees.salles.0']);
    }

    public function test_horaire_d_une_session_obligatoire_et_fin_apres_debut(): void
    {
        $cours = Cours::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->call('nouvelleSession', $cours->id)
            ->call('enregistrer')
            ->assertHasErrors(['donnees.jour', 'donnees.heure_debut', 'donnees.heure_fin'])
            ->set('donnees.jour', '4')
            ->set('donnees.heure_debut', '20:00')
            ->set('donnees.heure_fin', '18:00')
            ->call('enregistrer')
            ->assertHasErrors(['donnees.heure_fin' => 'after']);
    }

    public function test_un_groupe_avec_des_cours_ne_peut_pas_etre_supprime(): void
    {
        $cours = Cours::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->call('supprimer', 'groupe', $cours->groupe_id)
            ->assertSee('Supprimez d’abord les cours')
            ->call('supprimer', 'cours', $cours->id)
            ->call('supprimer', 'groupe', $cours->groupe_id);

        $this->assertSame(0, Groupe::count());
    }

    public function test_changer_l_ordre_des_groupes(): void
    {
        $a = Groupe::factory()->create(['ordre' => 1]);
        $b = Groupe::factory()->create(['ordre' => 2]);

        Livewire::actingAs($this->comite())
            ->test(GestionCours::class)
            ->call('deplacer', 'groupe', $b->id, -1);

        $this->assertSame([$b->id, $a->id], Groupe::dansLOrdre()->pluck('id')->all());
    }

    public function test_un_moniteur_ne_peut_rien_modifier(): void
    {
        Livewire::actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->test(GestionCours::class)
            ->assertForbidden();
    }
}
