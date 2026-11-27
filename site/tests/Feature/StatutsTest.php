<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Site\DocumentStatuts;
use App\Models\JournalModification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StatutsTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $contenu = '%PDF-1.4 statuts'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('statuts.pdf', $contenu);
    }

    public function test_sans_fichier_la_page_indique_que_les_statuts_arrivent(): void
    {
        Storage::fake('local');

        $this->get(route('societe.statuts'))
            ->assertOk()
            ->assertSee('Les statuts seront bientôt disponibles.')
            ->assertDontSee('Choisir un nouveau PDF');

        $this->get(route('societe.statuts.pdf'))->assertNotFound();
        $this->get(route('accueil'))->assertSee(route('societe.statuts'));
    }

    public function test_le_pdf_est_affiche_et_telechargeable(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(DocumentStatuts::CHEMIN, '%PDF-1.4 statuts');

        $this->get(route('societe.statuts'))
            ->assertOk()
            ->assertSee('Télécharger le PDF')
            ->assertSee('type="application/pdf"', false);

        $this->get(route('societe.statuts.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('attachment', $this->get(route('societe.statuts.pdf', ['telecharger' => 1]))->headers->get('Content-Disposition'));
    }

    public function test_le_comite_remplace_le_pdf_et_l_ancien_est_archive(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(DocumentStatuts::CHEMIN, '%PDF-1.4 ancien');

        Livewire::actingAs(User::factory()->avecRole(Role::Comite)->create())
            ->test(DocumentStatuts::class)
            ->set('fichier', $this->pdf('%PDF-1.4 nouveau'))
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('societe.statuts'));

        $this->assertSame('%PDF-1.4 nouveau', Storage::disk('local')->get(DocumentStatuts::CHEMIN));

        $entree = JournalModification::latest('id')->firstOrFail();
        $this->assertCount(1, $entree->fichiers);
        $this->assertSame('%PDF-1.4 ancien', Storage::disk('local')->get($entree->fichiers[0]['archive']));
    }

    public function test_un_faux_pdf_est_refuse(): void
    {
        Storage::fake('local');

        Livewire::actingAs(User::factory()->avecRole(Role::Comite)->create())
            ->test(DocumentStatuts::class)
            ->set('fichier', $this->pdf('<html>pas un pdf</html>'))
            ->call('enregistrer')
            ->assertHasErrors('fichier');

        Storage::disk('local')->assertMissing(DocumentStatuts::CHEMIN);
    }

    public function test_un_moniteur_ne_peut_pas_remplacer_les_statuts(): void
    {
        Livewire::actingAs(User::factory()->avecRole(Role::Moniteur)->create())
            ->test(DocumentStatuts::class)
            ->assertForbidden();
    }
}
