<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PhotoMembre;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert la photo d'un membre. Visible par tous seulement si le membre l'a choisi ;
 * sinon uniquement par le membre lui-même et par le comité.
 */
class PhotoMembreController extends Controller
{
    public function __invoke(Request $request, User $membre, PhotoMembre $photos): BinaryFileResponse
    {
        $lecteur = $request->user();

        $autorise = $membre->afficher_photo
            || ($lecteur !== null && ($lecteur->is($membre) || $lecteur->peutGererLesMembres()));

        $chemin = $photos->chemin($membre);

        abort_unless($autorise && $chemin !== null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $membre->afficher_photo ? 'public, max-age=86400' : 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
