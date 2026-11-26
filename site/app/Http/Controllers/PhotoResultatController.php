<?php

namespace App\Http\Controllers;

use App\Models\Resultat;
use App\Services\PhotosResultat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert une photo de résultat (ou sa miniature). Un résultat masqué n'est visible que par ceux qui publient.
 */
class PhotoResultatController extends Controller
{
    public function __invoke(Request $request, Resultat $resultat, int $index, PhotosResultat $photos): BinaryFileResponse
    {
        abort_unless($resultat->estAffichable() || ($request->user()?->can('publier-resultats') ?? false), 404);

        $chemin = $photos->chemin($resultat, $index, $request->query('taille') === 'miniature');

        abort_if($chemin === null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
