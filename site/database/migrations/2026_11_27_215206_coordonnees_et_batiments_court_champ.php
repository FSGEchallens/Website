<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordonnées GPS des bâtiments (la carte Google pointe sur le bâtiment), et Court-Champ séparé en 3 bâtiments :
 * « Court-Champ 1 » (Salle 1), « Court-Champ 2 » (Salle 2) et « Court-Champ 3-4 » (Salles 3 et 4).
 * Les identifiants des salles ne changent pas : les cours gardent leurs salles.
 */
return new class extends Migration
{
    private const COORDONNEES = [
        'Echallens, Court-Champ 1' => [46.636370, 6.633660],
        'Echallens, Court-Champ 2' => [46.637020, 6.632850],
        'Echallens, Court-Champ 3-4' => [46.636380, 6.632780],
        'Echallens, château' => [46.641340, 6.630150],
        'Poliez-le-Grand' => [46.636520, 6.669635],
    ];

    private const SEPARATION = [
        'Salle 1' => 'Echallens, Court-Champ 1',
        'Salle 2' => 'Echallens, Court-Champ 2',
        'Salle 3' => 'Echallens, Court-Champ 3-4',
        'Salle 4' => 'Echallens, Court-Champ 3-4',
    ];

    public function up(): void
    {
        Schema::table('batiments', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable()->after('localite');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
        });

        $courtChamp = DB::table('batiments')->where('nom', 'Echallens, Court-Champ')->first();

        if ($courtChamp !== null) {
            foreach (DB::table('salles')->where('batiment_id', $courtChamp->id)->get() as $salle) {
                $nouveauNom = self::SEPARATION[$salle->nom] ?? null;

                if ($nouveauNom === null) {
                    continue;
                }

                $id = DB::table('batiments')->where('nom', $nouveauNom)->value('id') ?? DB::table('batiments')->insertGetId([
                    'nom' => $nouveauNom,
                    'rue' => $courtChamp->rue, 'npa' => $courtChamp->npa, 'localite' => $courtChamp->localite,
                    'informations' => $courtChamp->informations,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                DB::table('salles')->where('id', $salle->id)->update(['batiment_id' => $id]);
            }

            // Les photos éventuelles vont au bâtiment des salles 3 et 4 ; l'ancien bâtiment vide disparaît.
            if (DB::table('salles')->where('batiment_id', $courtChamp->id)->doesntExist()) {
                DB::table('batiments')->where('nom', 'Echallens, Court-Champ 3-4')->whereNull('photos')->update(['photos' => $courtChamp->photos]);
                DB::table('batiments')->where('id', $courtChamp->id)->delete();
            }
        }

        foreach (self::COORDONNEES as $nom => [$latitude, $longitude]) {
            DB::table('batiments')->where('nom', $nom)->whereNull('latitude')->update(['latitude' => $latitude, 'longitude' => $longitude]);
        }
    }

    public function down(): void
    {
        Schema::table('batiments', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
