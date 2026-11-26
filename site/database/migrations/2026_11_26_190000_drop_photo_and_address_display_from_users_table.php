<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La photo est toujours affichée et l'adresse jamais : seul le choix « afficher le mobile » reste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['afficher_photo', 'afficher_adresse']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('afficher_photo')->default(false)->after('date_sortie');
            $table->boolean('afficher_adresse')->default(false)->after('afficher_photo');
        });
    }
};
