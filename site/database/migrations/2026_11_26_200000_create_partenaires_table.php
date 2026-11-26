<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partenaires affichés sur la page d'accueil, modifiables par le comité.
 * Les 9 partenaires de l'ancienne page sont repris (logos d'origine dans public/images/partenaires).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partenaires', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('url')->nullable();
            // « images/partenaires/… » = logo d'origine (dossier public) ; sinon fichier importé (stockage privé).
            $table->string('logo')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['visible', 'ordre']);
        });

        $partenaires = [
            ['FSG – Fédération suisse de gymnastique', 'https://www.stv-fsg.ch/fr', 'FSG_logo.jpg'],
            ['Gym Vaud – Association Cantonale Vaudoise de Gymnastique', 'https://www.gymvaud.ch', 'GYM_VD.jpg'],
            ['Commune d\'Echallens', 'https://www.echallens.ch/', 'Echallens_logo.jpg'],
            ['Raiffeisen', 'https://www.raiffeisen.ch/du-gros-de-vaud/fr.html', 'Raiffaisen_logo.png'],
            ['COOP', 'https://www.coop.ch', 'COOP.jpg'],
            ['Sports-Time', 'http://sports-time.ch/', 'Sports-Time_logo.png'],
            ['LAURENT Construction', 'http://www.laurentsa.ch', 'Laurent_Construction.png'],
            ['URBA Sanitaire', 'http://www.urba-sanitaire.ch', 'Urba-Sanitaire.jpg'],
            ['WEINMANN Energies', 'http://www.weinmann-energies.ch', 'Weinmann-Energies.png'],
        ];

        foreach ($partenaires as $position => [$nom, $url, $logo]) {
            DB::table('partenaires')->insert([
                'nom' => $nom,
                'url' => $url,
                'logo' => 'images/partenaires/'.$logo,
                'ordre' => $position + 1,
                'visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partenaires');
    }
};
