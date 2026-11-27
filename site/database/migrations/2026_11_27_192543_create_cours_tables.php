<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cours de gym : groupes (page publique, tarif) → cours (une fois par semaine) → sessions
 * (quand un même cours a plusieurs entraînements par semaine).
 *
 * `moniteurs` : liste ordonnée d'identifiants de membres ; le premier est le moniteur principal.
 * Sur une session, salle et moniteurs vides = ceux du cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('slug', 160)->unique();
            $table->string('categorie', 100)->nullable();
            $table->longText('description')->nullable();
            $table->string('age', 150)->nullable();
            $table->string('prerequis', 150)->nullable();
            $table->text('concours')->nullable();
            $table->decimal('prix', 8, 2)->nullable();
            $table->text('infos_tarif')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->restrictOnDelete();
            $table->string('nom', 150)->nullable();
            $table->unsignedTinyInteger('jour')->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->string('salle', 150)->nullable();
            $table->json('moniteurs')->nullable();
            $table->string('remarque', 255)->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('sessions_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->restrictOnDelete();
            $table->unsignedTinyInteger('jour');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle', 150)->nullable();
            $table->json('moniteurs')->nullable();
            $table->string('remarque', 255)->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_cours');
        Schema::dropIfExists('cours');
        Schema::dropIfExists('groupes');
    }
};
