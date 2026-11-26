<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résultats des concours (date, titre, texte mis en forme, 1 à 10 photos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->date('date_fin')->nullable();
            $table->string('titre', 200);
            $table->longText('contenu')->nullable();
            // Noms des fichiers photo, dans l'ordre d'affichage (10 au maximum).
            $table->json('photos')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['visible', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultats');
    }
};
