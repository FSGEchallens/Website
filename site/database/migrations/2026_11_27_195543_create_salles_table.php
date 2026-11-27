<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salles de gym (nom, adresse, photo, carte Google) : les cours et les sessions y sont reliés
 * par une liste déroulante. Les salles déjà saisies en texte libre sont reprises automatiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150)->unique();
            $table->string('rue', 150)->nullable();
            $table->string('npa', 10)->nullable();
            $table->string('localite', 100)->nullable();
            $table->string('lien_carte', 500)->nullable();
            $table->text('informations')->nullable();
            $table->string('photo', 255)->nullable();
            $table->timestamps();
        });

        foreach (['cours', 'sessions_cours'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('salle_id')->nullable()->after('heure_fin')->constrained('salles')->nullOnDelete();
            });
        }

        // Reprise des salles saisies en texte libre.
        $noms = DB::table('cours')->whereNotNull('salle')->pluck('salle')
            ->merge(DB::table('sessions_cours')->whereNotNull('salle')->pluck('salle'))
            ->map(fn ($nom) => trim((string) $nom))->filter()->unique()->sort()->values();

        foreach ($noms as $nom) {
            // « Echallens, Court-Champ 3 » → localité « Echallens » ; la rue et le NPA sont à compléter.
            $localite = trim((string) preg_split('/[,(]/', $nom)[0]);
            $id = DB::table('salles')->insertGetId(['nom' => $nom, 'localite' => $localite ?: null, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cours')->where('salle', $nom)->update(['salle_id' => $id]);
            DB::table('sessions_cours')->where('salle', $nom)->update(['salle_id' => $id]);
        }

        foreach (['cours', 'sessions_cours'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('salle');
            });
        }
    }

    public function down(): void
    {
        foreach (['cours', 'sessions_cours'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('salle', 150)->nullable()->after('heure_fin');
            });

            foreach (DB::table('salles')->get() as $salle) {
                DB::table($table)->where('salle_id', $salle->id)->update(['salle' => $salle->nom]);
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('salle_id');
            });
        }

        Schema::dropIfExists('salles');
    }
};
