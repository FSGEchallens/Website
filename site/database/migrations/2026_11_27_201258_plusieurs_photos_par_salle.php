<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jusqu'à 3 photos par salle : `photos` = liste ordonnée des noms de fichiers (JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('informations');
        });

        foreach (DB::table('salles')->whereNotNull('photo')->get(['id', 'photo']) as $salle) {
            DB::table('salles')->where('id', $salle->id)->update(['photos' => json_encode([$salle->photo])]);
        }

        Schema::table('salles', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }

    public function down(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->string('photo', 255)->nullable()->after('informations');
        });

        foreach (DB::table('salles')->whereNotNull('photos')->get(['id', 'photos']) as $salle) {
            DB::table('salles')->where('id', $salle->id)->update(['photo' => (json_decode((string) $salle->photos, true) ?: [])[0] ?? null]);
        }

        Schema::table('salles', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
