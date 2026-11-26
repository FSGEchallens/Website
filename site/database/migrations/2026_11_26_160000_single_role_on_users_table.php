<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passage à une autorisation unique par membre (colonne users.role).
 * Une personne qui avait plusieurs rôles garde le plus haut.
 */
return new class extends Migration
{
    /** @var array<string, int> */
    private array $niveaux = ['membre' => 1, 'moniteur' => 2, 'comite' => 3, 'administrateur' => 4];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('membre')->after('email');
            $table->index('role');
        });

        $rolesParMembre = DB::table('user_roles')->get(['user_id', 'role'])->groupBy('user_id');

        foreach ($rolesParMembre as $userId => $lignes) {
            $plusHaut = $lignes->sortByDesc(fn ($ligne) => $this->niveaux[$ligne->role] ?? 0)->first()->role;
            DB::table('users')->where('id', $userId)->update(['role' => $plusHaut]);
        }

        Schema::dropIfExists('user_roles');
    }

    public function down(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->unique(['user_id', 'role']);
            $table->index('role');
        });

        foreach (DB::table('users')->where('role', '!=', 'membre')->get(['id', 'role']) as $user) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id, 'role' => $user->role, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
