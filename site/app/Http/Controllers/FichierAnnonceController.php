<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Services\FichiersAnnonce;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert l'image et les fichiers joints (PDF…) des annonces.
 * Une annonce masquée ou hors période n'est accessible qu'au comité et à l'administrateur.
 */
class FichierAnnonceController extends Controller
{
    public function image(Annonce $annonce, FichiersAnnonce $fichiers): BinaryFileResponse
    {
        $this->verifierAcces($annonce);
        $chemin = $fichiers->cheminImage($annonce);

        abort_if($chemin === null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fichier(Annonce $annonce, int $index, FichiersAnnonce $fichiers): BinaryFileResponse
    {
        $this->verifierAcces($annonce);
        $lien = $annonce->liens[$index] ?? null;
        $chemin = ($lien['type'] ?? null) === 'fichier' ? $fichiers->cheminFichier($lien['fichier'] ?? null) : null;

        abort_if($chemin === null, 404);

        $nom = $lien['nom_original'] ?? basename($chemin);

        return response()->file($chemin, [
            'Content-Disposition' => 'inline; filename="'.str_replace(['"', "\r", "\n"], '', $nom).'"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function verifierAcces(Annonce $annonce): void
    {
        abort_unless($annonce->estActive() || (auth()->user()?->can('gerer-contenu') ?? false), 404);
    }
}
