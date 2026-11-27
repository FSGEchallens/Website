<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catégorie des groupes (pour le menu) : seulement « jeunesse » ou « adulte ».
 * Les groupes « dès 16 ans » (Agrès actifs plaisir, Gym-danse active) et l'ancienne catégorie « Adultes »
 * deviennent « adulte » ; tous les autres « jeunesse ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('groupes')->update(['categorie' => 'jeunesse']);
        DB::table('groupes')
            ->where(fn ($q) => $q->whereIn('slug', ['agres-actifs-plaisir-des-16-ans', 'gym-danse-active'])
                ->orWhere('nom', 'like', '%dès 16 ans%'))
            ->update(['categorie' => 'adulte']);

        foreach (['mise-en-forme', 'mardi-power', 'zumba', 'volleyball'] as $slug) {
            DB::table('groupes')->where('slug', $slug)->update(['categorie' => 'adulte']);
        }
    }

    public function down(): void
    {
        // Les anciennes catégories libres ne sont pas restaurées.
    }
};
