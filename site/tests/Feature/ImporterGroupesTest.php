<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImporterGroupesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Page fictive au format de l'ancien site (noms inventés).
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $creneaux  horaire, moniteur, lieu
     */
    private function page(array $creneaux, string $heures): string
    {
        $icone = fn (string $type, string $texte) => '<div class="et_pb_text_inner"><table><tr><td><strong><img src="https://exemple.ch/wp-content/uploads/Cours-logo-'.$type.'.jpg"></strong></td><td><p>'.$texte.'</p></td></tr></table></div>';

        $html = '<h1 class="entry-title">Groupe</h1><div class="et_pb_text_inner"><p><span style="font-size:16px">Super <strong>cours</strong></span></p></div>';
        $html .= $icone('membre', 'De 7 à 14 ans.').$icone('prequis', 'Aucun prérequis !');

        foreach ($creneaux as [$horaire, $moniteur, $lieu]) {
            $html .= $icone('duree', $horaire).$icone('monitrice', $moniteur).$icone('localisation', $lieu);
        }

        $html .= $icone('concours', 'Soirée de gym.');
        $html .= '<div class="et_pb_team_member_description"><h1>Anna Fictive</h1><a href="mailto:anna.fictive@gymechallens.ch">x</a></div>';
        $html .= '<span class="et_pb_sum">300</span><ul class="et_pb_pricing"><li><span>3 cours d\'essai</span></li><li><span>'.$heures.' de cours chaque semaine</span></li></ul>';

        return '<html><body>'.$html.'</body></html>';
    }

    public function test_import_des_groupes_cours_et_sessions(): void
    {
        $anna = User::factory()->avecRole(Role::Moniteur)->create(['email' => 'anna.fictive@gymechallens.ch']);

        Http::fake([
            'exemple.test/groupes/agres-kids-concours/' => Http::response($this->page([
                ['Mardi 18:00-20:00</p><p>COMPLET', 'Anna Fictive', 'Echallens, court-champs 3'],
                ['Jeudi 18:00-20:00</p><p>COMPLET', 'Anna Fictive', 'Echallens, court-champs 3'],
            ], '4h')),
            'exemple.test/groupes/enfantine/' => Http::response($this->page([
                ['Mardi 16:30-17:30', 'Bruno Inconnu', 'Echallens, court-champs 4'],
                ['Jeudi 16:30-17:30', 'Anna Fictive', 'Poliez-le-Grand (grande salle)'],
            ], '1h')),
            '*' => Http::response('', 404),
        ]);

        $this->artisan('groupes:importer', ['--site' => 'https://exemple.test'])
            ->expectsOutputToContain('Bruno Inconnu (Enfantine (1-2 Harmos))')
            ->assertSuccessful();

        $concours = Groupe::where('slug', 'agres-kids-concours')->firstOrFail();
        $this->assertSame('Agrès', $concours->categorie);
        $this->assertSame('300.00', $concours->prix);
        $this->assertSame('De 7 à 14 ans.', $concours->age);
        $this->assertSame('<p>Super <strong>cours</strong></p>', $concours->description);

        // 2 × 2 h = « 4h de cours chaque semaine » : un seul cours avec 2 sessions.
        $cours = $concours->cours()->with('sessions')->sole();
        $this->assertSame('Echallens, Court-Champ 3', $cours->salle);
        $this->assertSame([$anna->id], $cours->moniteurs);
        $this->assertSame('COMPLET', $cours->remarque);
        $this->assertSame(['Mardi 18:00–20:00', 'Jeudi 18:00–20:00'], $cours->sessions->map->libelleHoraire()->all());

        // 2 créneaux de 1 h pour « 1h » par semaine : deux cours séparés.
        $enfantine = Groupe::where('slug', 'enfantine-1-2-harmos')->firstOrFail();
        $this->assertSame(2, $enfantine->cours()->count());
        $this->assertSame([], $enfantine->cours()->first()->moniteurs);
        $this->assertSame('Poliez-le-Grand (Grande salle)', $enfantine->cours()->get()->last()->salle);

        // Relancer ne crée pas de doublon.
        $this->artisan('groupes:importer', ['--site' => 'https://exemple.test'])->assertSuccessful();
        $this->assertSame(2, Groupe::count());
        $this->assertSame(3, Cours::count());
    }
}
