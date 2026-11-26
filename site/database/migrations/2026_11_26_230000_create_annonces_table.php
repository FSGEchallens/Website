<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annonces affichées sous le bandeau de la page d'accueil (spectacle, événement…),
 * gérées par le comité. Affichage éventuellement limité à une période.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->longText('contenu')->nullable();
            $table->string('image')->nullable();
            $table->string('position_image', 10)->default('droite');
            // Boutons-liens : [{libelle, type (url|fichier), url, fichier, nom_original}]
            $table->json('liens')->nullable();
            $table->date('debut_le')->nullable();
            $table->date('fin_le')->nullable();
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['visible', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};
