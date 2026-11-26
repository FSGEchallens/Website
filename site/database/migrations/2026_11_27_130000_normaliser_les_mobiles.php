<?php

use App\Support\NumeroMobile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Met les mobiles déjà enregistrés au format « +41 79 123 45 67 ».
 * Les numéros qui ne sont pas des mobiles suisses sont laissés tels quels (à corriger dans la fiche).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('mobile')->orderBy('id')->each(function (object $membre): void {
            $normalise = NumeroMobile::normaliser($membre->mobile);

            if ($normalise !== null && $normalise !== $membre->mobile) {
                DB::table('users')->where('id', $membre->id)->update(['mobile' => $normalise]);
            }
        });
    }

    public function down(): void
    {
        // Le format +41 reste valable : rien à défaire.
    }
};
