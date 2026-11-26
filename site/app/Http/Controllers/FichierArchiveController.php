<?php

namespace App\Http\Controllers;

use App\Models\JournalModification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Affiche un fichier archivé dans le journal (aperçu d'une ancienne photo ou d'un ancien logo).
 * Réservé à l'administrateur.
 */
class FichierArchiveController extends Controller
{
    public function __invoke(JournalModification $entree, int $index): StreamedResponse
    {
        Gate::authorize('consulter-journal');

        $fichier = $entree->fichiers[$index] ?? null;

        abort_if($fichier === null || ! Storage::disk('local')->exists($fichier['archive']), 404);

        return Storage::disk('local')->response($fichier['archive'], basename($fichier['chemin']), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
