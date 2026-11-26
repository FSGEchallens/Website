<?php

use App\Livewire\Membres\FicheMembre;
use App\Livewire\Membres\ListeMembres;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    try {
        DB::connection()->getPdo();
        $baseOk = true;
    } catch (Throwable) {
        $baseOk = false;
    }

    return view('welcome', ['baseOk' => $baseOk]);
});

// Gestion des membres. TEMPORAIREMENT en accès libre (données fictives uniquement) :
// sera réservée au comité et au webmaster dès que la connexion sera en place.
Route::livewire('/membres', ListeMembres::class)->name('membres.index');
Route::livewire('/membres/nouveau', FicheMembre::class)->name('membres.create');
Route::livewire('/membres/{membre}/modifier', FicheMembre::class)->name('membres.edit');
