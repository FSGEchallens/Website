<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Page Contact : zone « Informations » modifiable par le comité, reprise de l'ancien site.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('blocs_contenu')->where('cle', 'contact.informations')->exists()) {
            return;
        }

        DB::table('blocs_contenu')->insert([
            'cle' => 'contact.informations',
            'libelle' => 'Contact – informations',
            'titre' => null,
            'contenu' => '<p><strong>Adresse de la société :</strong><br>FSG Echallens<br>Case postale 304<br>1040 Echallens</p>'
                .'<p><strong>Compte Raiffeisen :</strong><br>CH25 8080 8008 3074 6687 8</p>'
                .'<p>Une question ? Besoin d’aide ou de renseignements ? Contactez-nous.</p>'
                .'<p>Un problème avec le site : <a href="mailto:webmaster@gymechallens.ch">webmaster@gymechallens.ch</a></p>',
            'avec_titre' => false,
            'masquable' => false,
            'visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blocs_contenu')->where('cle', 'contact.informations')->delete();
    }
};
