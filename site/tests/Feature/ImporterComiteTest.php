<?php

namespace Tests\Feature;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImporterComiteTest extends TestCase
{
    use RefreshDatabase;

    private function fiche(string $nom, string $fonction, string $mobile, string $email): string
    {
        return '<div class="et_pb_team_member_description"><h1 class="et_pb_module_header">'.$nom.'</h1>'
            .'<p class="et_pb_member_position">'.$fonction.'</p><div><h3>Informations générales</h3>'
            .'<p><strong>N° Mobile:</strong> '.$mobile.'<br /> <strong>Adresse e-mail:</strong> <a href="mailto:'.$email.'">'.$email.'</a></p></div></div>';
    }

    public function test_import_du_comite(): void
    {
        // Une monitrice déjà importée (fiche existante) qui fait aussi partie du comité.
        $existante = User::factory()->avecRole(Role::Moniteur)->create(['prenom' => 'Léa', 'nom' => 'Fictive', 'email' => 'lea.fictive@gymechallens.ch']);
        User::factory()->create(['comite_membre' => true, 'comite_fonction' => 'Ancienne', 'comite_ordre' => 1]);

        Http::fake(['exemple.test/*' => Http::response('<html><body>'
            .$this->fiche('Anna Exemple', 'Présidente', '079 000 00 01', 'presidence@gymechallens.ch')
            .$this->fiche('Léa Fictive', 'Responsable technique', '078 000 00 02', 'technique@gymechallens.ch')
            .$this->fiche('Bruno Test', 'Caissier', '-', 'comptabilite@gymechallens.ch')
            .'</body></html>')]);

        $this->artisan('comite:importer', ['--url' => 'https://exemple.test/le-comite/'])
            ->expectsOutputToContain('Créé et ajouté au comité : Anna Exemple (Présidente)')
            ->expectsOutputToContain('Ajouté au comité : Léa Fictive (Responsable technique)')
            ->assertSuccessful();

        $anna = User::where('nom', 'Exemple')->sole();
        $this->assertTrue($anna->comite_membre);
        $this->assertSame('presidence@gymechallens.ch', $anna->comite_email);
        $this->assertNull($anna->email, 'L’e-mail de fonction ne devient pas l’identifiant de connexion.');
        $this->assertSame('+41 79 000 00 01', $anna->mobile);
        $this->assertSame(Role::Comite, $anna->role);
        $this->assertSame(Civilite::Madame, $anna->civilite);
        $this->assertSame(2, $anna->comite_ordre);

        $existante->refresh();
        $this->assertTrue($existante->comite_membre);
        $this->assertSame('technique@gymechallens.ch', $existante->comite_email);
        $this->assertSame(Role::Comite, $existante->role, 'Monitrice → Comité.');
        $this->assertSame('lea.fictive@gymechallens.ch', $existante->email);

        $bruno = User::where('nom', 'Test')->sole();
        $this->assertNull($bruno->mobile);
        $this->assertSame(Civilite::Monsieur, $bruno->civilite);
        $this->assertSame(['Ancienne', 'Présidente', 'Responsable technique', 'Caissier'], User::duComite()->pluck('comite_fonction')->all());

        // Relancer ne crée pas de doublon.
        $this->artisan('comite:importer', ['--url' => 'https://exemple.test/le-comite/'])->assertSuccessful();
        $this->assertSame(4, User::count());
        $this->assertSame(4, User::duComite()->count());
    }

    public function test_un_administrateur_reste_administrateur(): void
    {
        $admin = User::factory()->avecRole(Role::Administrateur)->create(['prenom' => 'Anna', 'nom' => 'Exemple']);

        Http::fake(['exemple.test/*' => Http::response($this->fiche('Anna Exemple', 'Présidente', '079 000 00 01', 'presidence@gymechallens.ch'))]);

        $this->artisan('comite:importer', ['--url' => 'https://exemple.test/le-comite/'])->assertSuccessful();

        $this->assertSame(Role::Administrateur, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->comite_membre);
    }
}
