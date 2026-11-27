<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composition affichée du comité (page « Le comité ») : indépendante du niveau d'autorisation,
 * pour gérer les transitions (ancien et nouveau président en même temps…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('comite_membre')->default(false)->after('afficher_mobile');
            $table->string('comite_fonction', 80)->nullable()->after('comite_membre');
            $table->string('comite_email')->nullable()->after('comite_fonction');
            $table->unsignedInteger('comite_ordre')->default(0)->after('comite_email');

            $table->index(['comite_membre', 'comite_ordre']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['comite_membre', 'comite_ordre']);
            $table->dropColumn(['comite_membre', 'comite_fonction', 'comite_email', 'comite_ordre']);
        });
    }
};
