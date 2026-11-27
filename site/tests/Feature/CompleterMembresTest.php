<?php

namespace Tests\Feature;

use App\Enums\Civilite;
use App\Models\JournalModification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CompleterMembresTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fichier Excel minimal (cellules en texte), avec des personnes inventées.
     *
     * @param  array<int, array<int, string>>  $lignes
     */
    private function xlsx(array $lignes): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($chemin, ZipArchive::OVERWRITE);

        $xml = '';

        foreach ($lignes as $i => $ligne) {
            $xml .= '<row r="'.($i + 1).'">';

            foreach ($ligne as $j => $valeur) {
                if ($valeur !== '') {
                    $xml .= '<c r="'.chr(65 + $j).($i + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($valeur).'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>');
        $zip->close();

        return (string) file_get_contents($chemin);
    }

    public function test_complete_seulement_les_membres_existants_sans_ecraser(): void
    {
        Storage::fake('local');

        $anna = User::factory()->create(['prenom' => 'Anna', 'nom' => 'Fictive', 'civilite' => null, 'rue' => null, 'numero' => null, 'npa' => null, 'localite' => null, 'mobile' => null, 'email_prive' => null]);
        $bruno = User::factory()->create(['prenom' => 'Bruno', 'nom' => 'Exemple', 'mobile' => '+41 79 111 11 11', 'email_prive' => null]);
        $avant = User::count();

        Storage::disk('local')->put('import/personnel.xlsx', $this->xlsx([
            ['Titre', 'Prénom', 'Nom', 'Rue', 'N°', 'Code postal', 'Localité', 'Mobile', 'E-Mail (privé)'],
            ['Madame', 'Anna', 'Fictive', 'Rue du Test', '12', '1040', 'Echallens', '079 000 00 01', 'Anna.Fictive@exemple.ch'],
            ['Monsieur', 'Bruno', 'Exemple', '', '', '', '', '078 222 22 22', 'pas-un-email'],
            ['Madame', 'Carla', 'Absente', 'Rue X', '1', '1000', 'Lausanne', '079 000 00 03', 'carla@exemple.ch'],
        ]));

        $this->artisan('membres:completer')
            ->expectsOutputToContain('Complété : Anna Fictive (titre, rue, n°, NPA, localité, mobile, e-mail privé)')
            ->expectsOutputToContain('Bruno Exemple : mobile déjà rempli, laissé tel quel')
            ->expectsOutputToContain('e-mail privé non valable')
            ->expectsOutputToContain(' - Carla Absente')
            ->assertSuccessful();

        $anna->refresh();
        $this->assertSame(Civilite::Madame, $anna->civilite);
        $this->assertSame('Rue du Test', $anna->rue);
        $this->assertSame('12', $anna->numero);
        $this->assertSame('1040', $anna->npa);
        $this->assertSame('+41 79 000 00 01', $anna->mobile);
        $this->assertSame('anna.fictive@exemple.ch', $anna->email_prive);

        $this->assertSame('+41 79 111 11 11', $bruno->fresh()->mobile, 'Un champ rempli n’est pas écrasé.');
        $this->assertSame($avant, User::count(), 'Personne n’est ajouté.');
        $this->assertTrue(JournalModification::where('sujet_id', $anna->id)->where('sujet_type', User::class)->exists());

        // Le fichier (données personnelles) est supprimé après l'import.
        Storage::disk('local')->assertMissing('import/personnel.xlsx');
    }

    public function test_remplacer_et_garder(): void
    {
        Storage::fake('local');
        $bruno = User::factory()->create(['prenom' => 'Bruno', 'nom' => 'Exemple', 'mobile' => '+41 79 111 11 11']);

        Storage::disk('local')->put('import/liste.csv', "Prénom;Nom;Mobile\nBruno;Exemple;078 222 22 22\n");

        $this->artisan('membres:completer', ['fichier' => 'import/liste.csv', '--remplacer' => true, '--garder' => true])->assertSuccessful();

        $this->assertSame('+41 78 222 22 22', $bruno->fresh()->mobile);
        Storage::disk('local')->assertExists('import/liste.csv');
    }

    public function test_fichier_absent(): void
    {
        Storage::fake('local');

        $this->artisan('membres:completer')->expectsOutputToContain('Fichier introuvable')->assertFailed();
    }
}
