<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Qui peut voir la photo d'un membre.
 */
class PhotoMembreTest extends TestCase
{
    use RefreshDatabase;

    private function membreAvecPhoto(bool $publique): User
    {
        Storage::fake('local');
        Storage::disk('local')->put('photos-membres/FSG-Echallens_Test_Anna.jpg', 'jpeg');

        return User::factory()->create(['photo' => 'FSG-Echallens_Test_Anna.jpg', 'afficher_photo' => $publique]);
    }

    public function test_photo_publique_visible_par_tous(): void
    {
        $membre = $this->membreAvecPhoto(publique: true);

        $this->get(route('membres.photo', $membre))->assertOk();
    }

    public function test_photo_privee_invisible_pour_les_visiteurs_et_les_autres_membres(): void
    {
        $membre = $this->membreAvecPhoto(publique: false);

        $this->get(route('membres.photo', $membre))->assertNotFound();
        $this->actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->get(route('membres.photo', $membre))->assertNotFound();
    }

    public function test_photo_privee_visible_par_le_membre_et_le_comite(): void
    {
        $membre = $this->membreAvecPhoto(publique: false);

        $this->actingAs($membre)->get(route('membres.photo', $membre))->assertOk();
        $this->actingAs(User::factory()->avecRole(Role::Comite)->create())
            ->get(route('membres.photo', $membre))->assertOk();
    }

    public function test_pas_de_photo_donne_404(): void
    {
        $membre = User::factory()->create(['afficher_photo' => true]);

        $this->get(route('membres.photo', $membre))->assertNotFound();
    }
}
