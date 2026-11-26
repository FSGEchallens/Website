<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PhotoMembre;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert la photo importée d'un membre. Les photos sont toujours publiques
 * (sans photo importée, le site affiche l'image par défaut homme ou femme).
 */
class PhotoMembreController extends Controller
{
    public function __invoke(User $membre, PhotoMembre $photos): BinaryFileResponse
    {
        $chemin = $photos->chemin($membre);

        abort_if($chemin === null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
