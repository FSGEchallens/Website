<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Historique : met en exposant les « 2e » et « 3e » (l'éditeur gère maintenant l'exposant).
 */
return new class extends Migration
{
    public function up(): void
    {
        $contenu = DB::table('blocs_contenu')->where('cle', 'societe.historique')->value('contenu');

        if ($contenu === null) {
            return;
        }

        $nouveau = preg_replace('/\b([23])e (groupe)/u', '$1<sup>e</sup> $2', $contenu);

        if ($nouveau !== null && $nouveau !== $contenu) {
            DB::table('blocs_contenu')->where('cle', 'societe.historique')->update(['contenu' => $nouveau, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $contenu = DB::table('blocs_contenu')->where('cle', 'societe.historique')->value('contenu');

        if ($contenu !== null) {
            DB::table('blocs_contenu')->where('cle', 'societe.historique')
                ->update(['contenu' => preg_replace('/([23])<sup>e<\/sup> (groupe)/u', '$1e $2', $contenu)]);
        }
    }
};
