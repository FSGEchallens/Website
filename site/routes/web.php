<?php

use App\Http\Controllers\PhotoMembreController;
use App\Livewire\Auth\Connexion;
use App\Livewire\Auth\MotDePasseOublie;
use App\Livewire\Auth\NouveauMotDePasse;
use App\Livewire\Auth\SaisieCode;
use App\Livewire\Membres\FicheMembre;
use App\Livewire\Membres\ListeMembres;
use App\Livewire\Profil\MonProfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'accueil')->name('accueil');

// Connexion en deux étapes (mot de passe puis code par e-mail) et mots de passe.
Route::middleware('guest')->group(function () {
    Route::livewire('/connexion', Connexion::class)->name('login');
    Route::livewire('/connexion/code', SaisieCode::class)->name('connexion.code');
    Route::livewire('/mot-de-passe-oublie', MotDePasseOublie::class)->name('password.request');
    Route::livewire('/reinitialiser-mot-de-passe/{token}', NouveauMotDePasse::class)->name('password.reset');
    Route::livewire('/bienvenue/{token}', NouveauMotDePasse::class)->name('bienvenue');
});

Route::post('/deconnexion', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('accueil');
})->middleware('auth')->name('logout');

Route::livewire('/profil', MonProfil::class)->middleware('auth')->name('profil');

// Photo d'un membre (toujours publique).
Route::get('/membres/{membre}/photo', PhotoMembreController::class)->name('membres.photo');

// Gestion des membres : comité et administrateur uniquement.
Route::middleware(['auth', 'can:gerer-membres'])->group(function () {
    Route::livewire('/membres', ListeMembres::class)->name('membres.index');
    Route::livewire('/membres/nouveau', FicheMembre::class)->name('membres.create');
    Route::livewire('/membres/{membre}/modifier', FicheMembre::class)->name('membres.edit');
});
