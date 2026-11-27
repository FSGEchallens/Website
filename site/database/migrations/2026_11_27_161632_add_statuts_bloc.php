<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bloc « La société – statuts » : il n'a pas de texte, il sert à inscrire au journal
 * les remplacements du PDF des statuts (stocké dans storage/app/private/documents/statuts.pdf).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('blocs_contenu')->where('cle', 'societe.statuts')->exists()) {
            return;
        }

        DB::table('blocs_contenu')->insert([
            'cle' => 'societe.statuts',
            'libelle' => 'La société – statuts (PDF)',
            'titre' => null,
            'contenu' => null,
            'avec_titre' => false,
            'masquable' => false,
            'visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blocs_contenu')->where('cle', 'societe.statuts')->delete();
    }
};
