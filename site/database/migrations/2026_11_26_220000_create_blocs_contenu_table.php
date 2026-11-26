<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blocs de texte du site modifiables par le comité (texte mis en forme, nettoyé côté serveur).
 * Reprise des textes de l'ancienne page d'accueil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocs_contenu', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 60)->unique();
            $table->string('libelle', 150);
            $table->string('titre', 150)->nullable();
            $table->longText('contenu')->nullable();
            $table->boolean('avec_titre')->default(false);
            $table->boolean('masquable')->default(false);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        $blocs = [
            [
                'cle' => 'accueil.texte',
                'libelle' => 'Accueil – texte de présentation',
                'contenu' => '<p>La FSG Echallens, anciennement GYM de la Bruyère fut fondée en 1921. Elle est aujourd’hui membre de l’ACVG (Association Cantonale Vaudoise de Gymnastique), ainsi que de la FSG (Fédération Suisse de Gymnastique). Nous proposons des cours de gymnastique de 3 à 99 ans, ainsi que des cours de volley.</p>'
                    .'<p>Nos groupes participent à divers concours que ce soit régionaux ou cantonaux tout au long de la saison, et tous les 5 ans nous participons à la cantonale de gymnastique. Nous participons à diverses manifestations locales sur Echallens et la société organise bisannuellement un spectacle où tous ses membres peuvent montrer à leur famille et amis le résultat de leur labeur.</p>',
            ],
            [
                'cle' => 'accueil.encart',
                'libelle' => 'Accueil – encadré gris',
                'contenu' => '<p>Venez découvrir nos cours et contactez nos moniteurs pour profiter de vos <strong>3 cours d’essai gratuits</strong>. La FSG Echallens, l’essayer c’est l’adopter !</p>',
            ],
            [
                'cle' => 'accueil.recrute',
                'libelle' => 'Accueil – La FSG Echallens recrute',
                'titre' => 'La FSG Echallens recrute !',
                'avec_titre' => true,
                'masquable' => true,
                'contenu' => '<p>La FSG Echallens recherche un moniteur pour son groupe Polysport kids. En cas d’intérêt, merci de contacter Marie Carvalhais sur <a href="mailto:presidence@gymechallens.ch">presidence@gymechallens.ch</a>.</p>',
            ],
        ];

        foreach ($blocs as $bloc) {
            DB::table('blocs_contenu')->insert([
                'titre' => null,
                'avec_titre' => false,
                'masquable' => false,
                ...$bloc,
                'visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocs_contenu');
    }
};
