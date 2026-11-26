<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code à 6 chiffres envoyé par e-mail lors de la connexion (2FA).
 * Le code est stocké haché, jamais en clair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code_connexion')->nullable()->after('remember_token');
            $table->timestamp('code_connexion_expire_le')->nullable()->after('code_connexion');
            $table->unsignedTinyInteger('code_connexion_tentatives')->default(0)->after('code_connexion_expire_le');
            $table->timestamp('derniere_connexion_le')->nullable()->after('code_connexion_tentatives');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['code_connexion', 'code_connexion_expire_le', 'code_connexion_tentatives', 'derniere_connexion_le']);
        });
    }
};
