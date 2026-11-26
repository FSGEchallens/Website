<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche membre de la société.
 *
 * Un membre = une ligne de la table users. La colonne `email` est l'e-mail
 * société (identifiant de connexion) : elle est facultative, car un membre
 * sans adresse @gymechallens.ch ne peut simplement pas se connecter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('civilite', 10)->nullable()->after('id');
            $table->string('prenom', 100)->after('civilite');
            $table->string('nom', 100)->after('prenom');
            $table->string('surnom', 100)->nullable()->after('nom');

            $table->string('rue', 150)->nullable()->after('surnom');
            $table->string('numero', 10)->nullable()->after('rue');
            $table->string('npa', 10)->nullable()->after('numero');
            $table->string('localite', 100)->nullable()->after('npa');
            $table->string('telephone', 20)->nullable()->after('localite');
            $table->string('mobile', 20)->nullable()->after('telephone');
            $table->string('email_prive')->nullable()->after('mobile');
            $table->string('photo')->nullable()->after('email_prive');

            // Chiffré par Laravel (voir le modèle User) : jamais lisible en clair dans la base.
            $table->text('avs')->nullable()->after('email');
            $table->date('date_naissance')->nullable()->after('avs');
            $table->date('date_entree')->nullable()->after('date_naissance');
            $table->date('date_sortie')->nullable()->after('date_entree');

            $table->boolean('afficher_photo')->default(false)->after('date_sortie');
            $table->boolean('afficher_adresse')->default(false)->after('afficher_photo');
            $table->boolean('afficher_telephone')->default(false)->after('afficher_adresse');
            $table->boolean('afficher_mobile')->default(false)->after('afficher_telephone');

            // Nom d'utilisateur sur l'ancien site WordPress, pour la migration.
            $table->string('compte_wp', 60)->nullable()->after('afficher_mobile');

            $table->index(['nom', 'prenom']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['nom', 'prenom']);
            $table->dropColumn([
                'civilite', 'prenom', 'nom', 'surnom', 'rue', 'numero', 'npa', 'localite',
                'telephone', 'mobile', 'email_prive', 'photo', 'avs', 'date_naissance',
                'date_entree', 'date_sortie', 'afficher_photo', 'afficher_adresse',
                'afficher_telephone', 'afficher_mobile', 'compte_wp',
            ]);
            $table->string('name')->default('')->after('id');
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
