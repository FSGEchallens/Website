<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Cours\GestionSalles;
use App\Models\Cours;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GestionSallesTest extends TestCase
{
    use RefreshDatabase;

    private function comite(): User
    {
        return User::factory()->avecRole(Role::Comite)->create();
    }

    public function test_la_page_est_reservee_au_comite(): void
    {
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())->get(route('salles.gestion'))->assertForbidden();
        $this->actingAs($this->comite())->get(route('salles.gestion'))->assertOk()->assertSee('Salles');
    }

    public function test_creer_une_salle_avec_adresse_photo_et_carte(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->comite())
            ->test(GestionSalles::class)
            ->call('ajouter')
            ->set('nom', 'Echallens, Court-Champ 3')
            ->set('rue', 'Chemin du Collège 3')
            ->set('npa', '1040')
            ->set('localite', 'Echallens')
            ->assertSee('maps.google.com/maps?q=Chemin%20du%20Coll%C3%A8ge%203%2C%201040%20Echallens', false)
            ->set('informations', 'Entrée côté parking')
            ->set('photo', UploadedFile::fake()->image('salle.jpg', 2400, 1800))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $salle = Salle::firstOrFail();
        $this->assertSame('Chemin du Collège 3, 1040 Echallens', $salle->adresseComplete());
        $this->assertNotNull($salle->photo);
        Storage::disk('local')->assertExists('salles/'.$salle->photo);

        [$largeur] = getimagesizefromstring(Storage::disk('local')->get('salles/'.$salle->photo));
        $this->assertSame(1600, $largeur);

        $this->get($salle->urlPhoto())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('google.com/maps/search', (string) $salle->urlCarte());
    }

    public function test_le_npa_doit_avoir_4_chiffres(): void
    {
        Livewire::actingAs($this->comite())
            ->test(GestionSalles::class)
            ->call('ajouter')
            ->set('nom', 'Salle')
            ->set('npa', '10a')
            ->call('enregistrer')
            ->assertHasErrors('npa');
    }

    public function test_une_salle_utilisee_ne_peut_pas_etre_supprimee(): void
    {
        $salle = Salle::factory()->create();
        Cours::factory()->create(['salle_id' => $salle->id]);
        $libre = Salle::factory()->create();

        Livewire::actingAs($this->comite())
            ->test(GestionSalles::class)
            ->call('supprimer', $salle->id)
            ->assertSee('est utilisée par des cours')
            ->call('supprimer', $libre->id);

        $this->assertSame([$salle->id], Salle::pluck('id')->all());
    }
}
