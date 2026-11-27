<?php

namespace Tests\Feature;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Models\Batiment;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Salle;
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
        $html .= '<div class="et_pb_team_member_description"><h1>Carla Exemple</h1><p class="et_pb_member_position">Monitrice</p>'
            .'<p><strong>N° Mobile:</strong> 079 000 00 01</p><p>Adresse e-mail:<a href="mailto:carla.exemple@gymechallens.ch">x</a></p></div>';
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
                ['Mardi 16:30-17:30', 'Bruno Inconnu', 'Echallens, court-champs 3+4'],
                ['Jeudi 16:30-17:30', 'Anna Fictive', 'Poliez-le-Grand (grande salle)'],
            ], '1h')),
            '*' => Http::response('', 404),
        ]);

        $this->artisan('groupes:importer', ['--site' => 'https://exemple.test'])
            ->expectsOutputToContain('+ Bruno Inconnu (Enfantine (1-2 Harmos))')
            ->assertSuccessful();

        // Fiches membres créées pour les moniteurs qui n'en avaient pas (sans mot de passe ni invitation).
        $carla = User::where('nom', 'Exemple')->sole();
        $this->assertSame('Carla', $carla->prenom);
        $this->assertSame(Role::Moniteur, $carla->role);
        $this->assertSame(Civilite::Madame, $carla->civilite);
        $this->assertSame('carla.exemple@gymechallens.ch', $carla->email);
        $this->assertSame('+41 79 000 00 01', $carla->mobile);
        $this->assertNull($carla->password);
        $bruno = User::where('nom', 'Inconnu')->sole();
        $this->assertNull($bruno->email);
        $this->assertSame(1, User::where('email', 'anna.fictive@gymechallens.ch')->count(), 'Anna existait déjà.');

        $concours = Groupe::where('slug', 'agres-kids-concours')->firstOrFail();
        $this->assertSame('Agrès', $concours->categorie);
        $this->assertSame('300.00', $concours->prix);
        $this->assertSame('De 7 à 14 ans.', $concours->age);
        $this->assertSame('<p>Super <strong>cours</strong></p>', $concours->description);

        // 2 × 2 h = « 4h de cours chaque semaine » : un seul cours avec 2 sessions.
        $cours = $concours->cours()->with('sessions')->sole();
        $courtChamp = Batiment::where('nom', 'Echallens, Court-Champ')->sole();
        $salle3 = $courtChamp->salles()->where('nom', 'Salle 3')->sole();
        $this->assertSame([$salle3->id], $cours->salles);
        $this->assertSame('Echallens', $courtChamp->localite);
        // Carla est présentée sur la page sans créneau : moniteur secondaire.
        $this->assertSame([$anna->id, $carla->id], $cours->moniteurs);
        $this->assertSame('COMPLET', $cours->remarque);
        $this->assertSame(['Mardi 18:00–20:00', 'Jeudi 18:00–20:00'], $cours->sessions->map->libelleHoraire()->all());

        // 2 créneaux de 1 h pour « 1h » par semaine : deux cours séparés.
        $enfantine = Groupe::where('slug', 'enfantine-1-2-harmos')->firstOrFail();
        $this->assertSame(2, $enfantine->cours()->count());
        $this->assertSame([$bruno->id, $carla->id], $enfantine->cours()->first()->moniteurs);
        // « Court-Champ 3+4 » = les deux salles de la salle double, dans le même bâtiment.
        $this->assertSame([$salle3->id, $courtChamp->salles()->where('nom', 'Salle 4')->sole()->id], $enfantine->cours()->first()->salles);
        $poliez = Batiment::where('nom', 'Poliez-le-Grand')->sole();
        $this->assertSame($poliez->salles()->where('nom', 'Grande salle')->pluck('id')->all(), $enfantine->cours()->get()->last()->salles);
        $this->assertSame(2, Batiment::count());
        $this->assertSame(3, Salle::count());

        // Relancer ne crée pas de doublon.
        $this->artisan('groupes:importer', ['--site' => 'https://exemple.test'])->assertSuccessful();
        $this->assertSame(2, Groupe::count());
        $this->assertSame(3, Cours::count());
        $this->assertSame(3, User::count());
    }

    public function test_relancer_complete_les_moniteurs_des_groupes_deja_importes(): void
    {
        Http::fake([
            'exemple.test/groupes/agres-kids-concours/' => Http::response($this->page([
                ['Mardi 18:00-20:00', 'Anna Fictive', 'Echallens, court-champs 3'],
                ['Jeudi 18:00-20:00', 'Anna Fictive', 'Echallens, court-champs 3'],
            ], '4h')),
            'exemple.test/groupes/enfantine/' => Http::response($this->page([
                ['Mardi 16:30-17:30', 'Bruno Inconnu', 'Echallens, court-champs 4'],
                ['Jeudi 16:30-17:30', 'Anna Fictive', 'Echallens, court-champs 4'],
            ], '1h')),
            '*' => Http::response('', 404),
        ]);

        // Premier import sans créer de fiches : aucun moniteur n'est relié.
        $this->artisan('groupes:importer', ['--site' => 'https://exemple.test', '--sans-membres' => true])->assertSuccessful();
        $this->assertSame(0, User::count());
        $this->assertTrue(Cours::all()->every(fn (Cours $c) => $c->idsMoniteurs() === []));

        // Second passage : les fiches sont créées et les cours complétés, sans doublon de groupe ni de cours.
        $this->artisan('groupes:importer', ['--site' => 'https://exemple.test'])
            ->expectsOutputToContain('Déjà présent : Enfantine (1-2 Harmos) (moniteurs ajoutés sur 2 créneau(x))')
            ->assertSuccessful();

        $anna = User::where('email', 'anna.fictive@gymechallens.ch')->sole();
        $bruno = User::where('nom', 'Inconnu')->sole();
        $carla = User::where('nom', 'Exemple')->sole();
        $enfantine = Groupe::where('slug', 'enfantine-1-2-harmos')->firstOrFail()->cours()->get();
        $this->assertSame([$bruno->id, $carla->id], $enfantine[0]->moniteurs);
        $this->assertSame([$anna->id, $carla->id], $enfantine[1]->moniteurs);
        $this->assertSame([$anna->id, $carla->id], Groupe::where('slug', 'agres-kids-concours')->firstOrFail()->cours()->sole()->moniteurs);
        $this->assertSame(2, Groupe::count());
        $this->assertSame(3, Cours::count());
    }
}
