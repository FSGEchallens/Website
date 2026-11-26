<?php

use App\Enums\Role;
use App\Http\Controllers\FichierAnnonceController;
use App\Http\Controllers\FichierArchiveController;
use App\Http\Controllers\LogoPartenaireController;
use App\Http\Controllers\PhotoMembreController;
use App\Http\Controllers\PhotoResultatController;
use App\Livewire\Administration\JournalModifications;
use App\Livewire\Auth\Connexion;
use App\Livewire\Auth\MotDePasseOublie;
use App\Livewire\Auth\NouveauMotDePasse;
use App\Livewire\Auth\SaisieCode;
use App\Livewire\Membres\FicheMembre;
use App\Livewire\Membres\ListeMembres;
use App\Livewire\Profil\MonProfil;
use App\Livewire\Site\GestionAnnonces;
use App\Livewire\Site\GestionPartenaires;
use App\Livewire\Site\GestionResultats;
use App\Models\Annonce;
use App\Models\BlocContenu;
use App\Models\Partenaire;
use App\Models\Resultat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('accueil', [
    'partenaires' => Partenaire::visibles()->dansLOrdre()->get(),
    'blocs' => BlocContenu::parCles(['accueil.texte', 'accueil.encart', 'accueil.recrute']),
    'annonces' => Annonce::actives()->dansLOrdre()->get(),
]))->name('accueil');

// La société : pages de texte modifiables par le comité.
Route::get('/la-societe/historique', fn () => view('pages.contenu', [
    'titre' => 'Historique',
    'cle' => 'societe.historique',
    'bloc' => BlocContenu::where('cle', 'societe.historique')->firstOrFail(),
    'image' => 'images/societe/Gym-Bruyere_logo.png',
    'texteImage' => 'Logo historique de la Gym de la Bruyère',
]))->name('societe.historique');

// Résultats des concours (page publique, du plus récent au plus ancien).
Route::get('/la-societe/resultats', fn () => view('resultats', [
    'resultats' => Resultat::where('visible', true)->duPlusRecent()->paginate(10),
]))->name('resultats.index');
Route::get('/resultats/{resultat}/photos/{index}', PhotoResultatController::class)->whereNumber('index')->name('resultats.photo');

// Image et fichiers joints des annonces.
Route::get('/annonces/{annonce}/image', [FichierAnnonceController::class, 'image'])->name('annonces.image');
Route::get('/annonces/{annonce}/fichiers/{index}', [FichierAnnonceController::class, 'fichier'])->whereNumber('index')->name('annonces.fichier');

// Logo importé d'un partenaire (public).
Route::get('/partenaires/{partenaire}/logo', LogoPartenaireController::class)->name('partenaires.logo');

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

// « Voir le site comme… » : réservé au compte webmaster (administrateur), uniquement vers un niveau inférieur.
Route::post('/apercu-niveau', function (Request $request) {
    $utilisateur = $request->user();
    abort_unless($utilisateur->peutChangerDeNiveau(), 403);

    $niveau = Role::tryFrom((string) $request->input('niveau'));
    abort_if($niveau === null, 422);

    if ($niveau === $utilisateur->roleReel()) {
        $request->session()->forget('apercu_niveau');
    } else {
        $request->session()->put('apercu_niveau', $niveau->value);
    }

    return redirect()->back();
})->middleware('auth')->name('apercu-niveau');

// Photo d'un membre (toujours publique).
Route::get('/membres/{membre}/photo', PhotoMembreController::class)->name('membres.photo');

// Gestion des membres : comité et administrateur uniquement.
Route::middleware(['auth', 'can:gerer-membres'])->group(function () {
    Route::livewire('/membres', ListeMembres::class)->name('membres.index');
    Route::livewire('/membres/nouveau', FicheMembre::class)->name('membres.create');
    Route::livewire('/membres/{membre}/modifier', FicheMembre::class)->name('membres.edit');
});

// Journal des modifications : administrateur uniquement.
Route::middleware(['auth', 'can:consulter-journal'])->group(function () {
    Route::livewire('/administration/journal', JournalModifications::class)->name('journal.index');
    Route::get('/administration/journal/{entree}/fichier/{index}', FichierArchiveController::class)
        ->whereNumber('index')->name('journal.fichier');
});

// Résultats : moniteurs, comité et administrateur.
Route::middleware(['auth', 'can:publier-resultats'])->group(function () {
    Route::livewire('/gestion/resultats', GestionResultats::class)->name('resultats.gestion');
});

// Contenus du site public : comité et administrateur uniquement.
Route::middleware(['auth', 'can:gerer-contenu'])->group(function () {
    Route::livewire('/gestion/partenaires', GestionPartenaires::class)->name('partenaires.index');
    Route::livewire('/gestion/annonces', GestionAnnonces::class)->name('annonces.index');
});
