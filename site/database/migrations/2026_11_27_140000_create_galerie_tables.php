<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galerie : albums et photos. Les suppressions passent par une corbeille (soft delete) ;
 * les éléments sont effacés définitivement après JOURNAL_CONSERVATION_JOURS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->string('slug', 170)->unique();
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('couverture_id')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['visible', 'date']);
        });

        Schema::create('photos_galerie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('fichier');
            $table->string('nom_original')->nullable();
            $table->unsignedSmallInteger('largeur')->nullable();
            $table->unsignedSmallInteger('hauteur')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['album_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos_galerie');
        Schema::dropIfExists('albums');
    }
};
