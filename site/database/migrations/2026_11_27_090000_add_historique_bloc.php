<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Page « La société → Historique » : texte modifiable par le comité, repris de l'ancien site.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('blocs_contenu')->where('cle', 'societe.historique')->exists()) {
            return;
        }

        $contenu = '<ul>'
            .'<li><strong>6 avril 1921</strong> : fondation de LA BRUYÈRE Echallens, sous-section de la Société de Gymnastique.</li>'
            .'<li><strong>1935</strong> : admission à l’Association cantonale.</li>'
            .'<li><strong>1942</strong> : création d’un groupe de dames et d’une classe de pupillettes.</li>'
            .'<li>…</li>'
            .'<li><strong>2016</strong> : ouverture d’un 3e groupe parent-enfant.</li>'
            .'<li><strong>2017</strong> : ouverture d’un 2e groupe enfantine.</li>'
            .'<li><strong>2018</strong> : ouverture d’un 3e groupe enfantine.</li>'
            .'<li><strong>2019</strong> : lancement du nouveau site internet, ouverture d’un groupe athlétisme kids et d’un groupe course à pied.</li>'
            .'<li><strong>2021</strong> : ouverture d’un groupe jonglerie.</li>'
            .'</ul>';

        DB::table('blocs_contenu')->insert([
            'cle' => 'societe.historique',
            'libelle' => 'La société – historique',
            'titre' => null,
            'contenu' => $contenu,
            'avec_titre' => false,
            'masquable' => false,
            'visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blocs_contenu')->where('cle', 'societe.historique')->delete();
    }
};
