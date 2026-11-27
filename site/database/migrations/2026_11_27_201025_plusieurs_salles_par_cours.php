<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un cours (ou une session) peut utiliser plusieurs salles : ex. salle double séparable Court-Champ 3 / 4,
 * utilisée en 3, en 4 ou les deux. `salles` = liste d'identifiants de salles (JSON), comme `moniteurs`.
 * Une salle combinée « … 3+4 » est remplacée par les deux salles « … 3 » et « … 4 ».
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cours', 'sessions_cours'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('salles')->nullable()->after('heure_fin');
            });

            foreach (DB::table($table)->whereNotNull('salle_id')->get(['id', 'salle_id']) as $ligne) {
                DB::table($table)->where('id', $ligne->id)->update(['salles' => json_encode([(int) $ligne->salle_id])]);
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('salle_id');
            });
        }

        // Salles combinées « Echallens, Court-Champ 3+4 » → « … 3 » et « … 4 ».
        foreach (DB::table('salles')->get() as $salle) {
            if (! preg_match('/^(.*?)(\d+)\s*\+\s*(\d+)(.*)$/u', $salle->nom, $m)) {
                continue;
            }

            $ids = [];

            foreach ([$m[2], $m[3]] as $numero) {
                $nom = trim($m[1].$numero.$m[4]);
                $existante = DB::table('salles')->where('nom', $nom)->value('id');
                $ids[] = (int) ($existante ?? DB::table('salles')->insertGetId([
                    'nom' => $nom, 'localite' => $salle->localite, 'created_at' => now(), 'updated_at' => now(),
                ]));
            }

            foreach (['cours', 'sessions_cours'] as $table) {
                foreach (DB::table($table)->whereNotNull('salles')->get(['id', 'salles']) as $ligne) {
                    $salles = json_decode((string) $ligne->salles, true) ?: [];

                    if (in_array((int) $salle->id, $salles, true)) {
                        $nouvelles = array_values(array_unique(array_merge(array_diff($salles, [(int) $salle->id]), $ids)));
                        DB::table($table)->where('id', $ligne->id)->update(['salles' => json_encode($nouvelles)]);
                    }
                }
            }

            DB::table('salles')->where('id', $salle->id)->delete();
        }
    }

    public function down(): void
    {
        foreach (['cours', 'sessions_cours'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('salle_id')->nullable()->after('heure_fin')->constrained('salles')->nullOnDelete();
            });

            foreach (DB::table($table)->whereNotNull('salles')->get(['id', 'salles']) as $ligne) {
                $premiere = (json_decode((string) $ligne->salles, true) ?: [])[0] ?? null;
                DB::table($table)->where('id', $ligne->id)->update(['salle_id' => $premiere]);
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('salles');
            });
        }
    }
};
