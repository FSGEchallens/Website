<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deux niveaux : le bâtiment (adresse, carte Google, photos, informations) et les salles qu'il contient.
 * Les identifiants des salles ne changent pas : les cours et les sessions gardent leurs salles.
 *
 * Reprise des salles existantes :
 * - « Echallens, Court-Champ 3 » → bâtiment « Echallens, Court-Champ », salle « Salle 3 » ;
 * - « Poliez-le-Grand (Grande salle) » → bâtiment « Poliez-le-Grand », salle « Grande salle » ;
 * - sinon → bâtiment du même nom, salle « Salle principale ».
 * L'adresse, la carte, les informations et les photos passent au bâtiment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batiments', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150)->unique();
            $table->string('rue', 150)->nullable();
            $table->string('npa', 10)->nullable();
            $table->string('localite', 100)->nullable();
            $table->string('lien_carte', 500)->nullable();
            $table->text('informations')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        Schema::table('salles', function (Blueprint $table) {
            $table->foreignId('batiment_id')->nullable()->after('id')->constrained('batiments')->restrictOnDelete();
            $table->unsignedInteger('ordre')->default(0)->after('nom');
        });

        $disque = Storage::disk('local');

        foreach (DB::table('salles')->orderBy('nom')->get() as $salle) {
            [$nomBatiment, $nomSalle] = match (true) {
                (bool) preg_match('/^(.*?)\s*(\d+)$/u', $salle->nom, $m) => [$m[1], 'Salle '.$m[2]],
                (bool) preg_match('/^(.*?)\s*\((.+)\)$/u', $salle->nom, $m) => [$m[1], mb_strtoupper(mb_substr($m[2], 0, 1)).mb_substr($m[2], 1)],
                default => [$salle->nom, 'Salle principale'],
            };
            $nomBatiment = trim($nomBatiment, ' ,') ?: $salle->nom;

            $batiment = DB::table('batiments')->where('nom', $nomBatiment)->first();

            if ($batiment === null) {
                $id = DB::table('batiments')->insertGetId([
                    'nom' => $nomBatiment, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $batiment = DB::table('batiments')->find($id);
            }

            // Ce que le bâtiment n'a pas encore (adresse, carte, informations, photos) est repris de la salle.
            $complements = [];

            foreach (['rue', 'npa', 'localite', 'lien_carte', 'informations'] as $champ) {
                if ($batiment->{$champ} === null && $salle->{$champ} !== null) {
                    $complements[$champ] = $salle->{$champ};
                }
            }

            $photos = json_decode((string) $salle->photos, true) ?: [];
            $photosBatiment = json_decode((string) $batiment->photos, true) ?: [];

            foreach ($photos as $photo) {
                foreach ([$photo, preg_replace('/\.jpg$/', '_miniature.jpg', $photo)] as $fichier) {
                    if ($disque->exists('salles/'.$fichier)) {
                        $disque->move('salles/'.$fichier, 'batiments/'.$fichier);
                    }
                }

                if (count($photosBatiment) < 3) {
                    $photosBatiment[] = $photo;
                }
            }

            if ($photos !== []) {
                $complements['photos'] = json_encode($photosBatiment);
            }

            if ($complements !== []) {
                DB::table('batiments')->where('id', $batiment->id)->update($complements);
            }

            $numero = DB::table('salles')->where('batiment_id', $batiment->id)->count() + 1;
            DB::table('salles')->where('id', $salle->id)->update(['batiment_id' => $batiment->id, 'nom' => $nomSalle, 'ordre' => $numero]);
        }

        Schema::table('salles', function (Blueprint $table) {
            $table->dropUnique(['nom']);
        });

        Schema::table('salles', function (Blueprint $table) {
            $table->dropColumn(['rue', 'npa', 'localite', 'lien_carte', 'informations', 'photos']);
            $table->unique(['batiment_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->dropUnique(['batiment_id', 'nom']);
            $table->string('rue', 150)->nullable();
            $table->string('npa', 10)->nullable();
            $table->string('localite', 100)->nullable();
            $table->string('lien_carte', 500)->nullable();
            $table->text('informations')->nullable();
            $table->json('photos')->nullable();
        });

        foreach (DB::table('salles')->get() as $salle) {
            $batiment = DB::table('batiments')->find($salle->batiment_id);
            DB::table('salles')->where('id', $salle->id)->update([
                'nom' => trim(($batiment->nom ?? '').' '.$salle->nom),
                'rue' => $batiment->rue ?? null, 'npa' => $batiment->npa ?? null, 'localite' => $batiment->localite ?? null,
            ]);
        }

        Schema::table('salles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batiment_id');
            $table->dropColumn('ordre');
            $table->unique('nom');
        });

        Schema::dropIfExists('batiments');
    }
};
