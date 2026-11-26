<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des modifications (qui a modifié quoi), avec les valeurs avant / après
 * et les fichiers archivés, pour pouvoir restaurer. Conservé JOURNAL_CONSERVATION_JOURS jours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('sujet_type');
            $table->unsignedBigInteger('sujet_id');
            $table->string('libelle');
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->json('fichiers')->nullable();
            $table->string('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['sujet_type', 'sujet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_modifications');
    }
};
