<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les photos importées sont toujours publiques.
 */
class PhotoMembreTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_importee_visible_par_tous(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('photos-membres/FSG-Echallens_Test_Anna.jpg', 'jpeg');
        $membre = User::factory()->create(['photo' => 'FSG-Echallens_Test_Anna.jpg']);

        $this->get(route('membres.photo', $membre))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_sans_photo_importee_l_adresse_renvoie_404_et_le_site_utilise_l_image_par_defaut(): void
    {
        $membre = User::factory()->create(['photo' => null]);

        $this->get(route('membres.photo', $membre))->assertNotFound();
        $this->assertStringContainsString('images/avatars/', $membre->urlPhoto());
    }
}
