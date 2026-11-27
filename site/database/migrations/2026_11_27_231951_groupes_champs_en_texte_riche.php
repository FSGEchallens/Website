<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Âge, prérequis, concours et informations du tarif d'un groupe : texte mis en forme (éditeur de texte),
 * comme la description. Les textes existants sont convertis en HTML ; les informations du tarif
 * (une par ligne) deviennent une liste à puces. Idem pour la remarque affichée des cours et des sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groupes', function (Blueprint $table) {
            $table->text('age')->nullable()->change();
            $table->text('prerequis')->nullable()->change();
        });

        foreach (DB::table('groupes')->get(['id', 'age', 'prerequis', 'concours', 'infos_tarif']) as $groupe) {
            $valeurs = [];

            foreach (['age', 'prerequis', 'concours'] as $champ) {
                $texte = trim((string) $groupe->{$champ});

                if ($texte !== '' && ! str_starts_with($texte, '<')) {
                    $valeurs[$champ] = '<p>'.nl2br(htmlspecialchars($texte, ENT_QUOTES, 'UTF-8'), false).'</p>';
                }
            }

            $tarif = trim((string) $groupe->infos_tarif);

            if ($tarif !== '' && ! str_starts_with($tarif, '<')) {
                $lignes = array_filter(array_map('trim', preg_split('/\r?\n/', $tarif) ?: []));
                $valeurs['infos_tarif'] = '<ul>'.implode('', array_map(fn ($l) => '<li>'.htmlspecialchars($l, ENT_QUOTES, 'UTF-8').'</li>', $lignes)).'</ul>';
            }

            if ($valeurs !== []) {
                DB::table('groupes')->where('id', $groupe->id)->update($valeurs);
            }
        }

        // Remarque affichée des cours et des sessions (ex. « COMPLET pour 2026-2027 ») : éditeur de texte aussi.
        foreach (['cours', 'sessions_cours'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->text('remarque')->nullable()->change();
            });

            foreach (DB::table($table)->whereNotNull('remarque')->get(['id', 'remarque']) as $ligne) {
                $texte = trim((string) $ligne->remarque);

                if ($texte !== '' && ! str_starts_with($texte, '<')) {
                    DB::table($table)->where('id', $ligne->id)->update(['remarque' => '<p>'.htmlspecialchars($texte, ENT_QUOTES, 'UTF-8').'</p>']);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['cours', 'sessions_cours'] as $table) {
            foreach (DB::table($table)->whereNotNull('remarque')->get(['id', 'remarque']) as $ligne) {
                $texte = trim(html_entity_decode(strip_tags((string) $ligne->remarque), ENT_QUOTES, 'UTF-8'));
                DB::table($table)->where('id', $ligne->id)->update(['remarque' => mb_substr($texte, 0, 255) ?: null]);
            }

            Schema::table($table, function (Blueprint $t) {
                $t->string('remarque', 255)->nullable()->change();
            });
        }

        foreach (DB::table('groupes')->get(['id', 'age', 'prerequis', 'concours', 'infos_tarif']) as $groupe) {
            $texte = fn (?string $html) => $html === null ? null : trim(html_entity_decode(strip_tags(str_replace(['</li>', '<br>', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));

            DB::table('groupes')->where('id', $groupe->id)->update([
                'age' => mb_substr((string) $texte($groupe->age), 0, 150) ?: null,
                'prerequis' => mb_substr((string) $texte($groupe->prerequis), 0, 150) ?: null,
                'concours' => $texte($groupe->concours),
                'infos_tarif' => $texte($groupe->infos_tarif),
            ]);
        }

        Schema::table('groupes', function (Blueprint $table) {
            $table->string('age', 150)->nullable()->change();
            $table->string('prerequis', 150)->nullable()->change();
        });
    }
};
