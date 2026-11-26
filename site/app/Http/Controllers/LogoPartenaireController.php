<?php

namespace App\Http\Controllers;

use App\Models\Partenaire;
use App\Services\LogoPartenaire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert le logo importé d'un partenaire (public).
 */
class LogoPartenaireController extends Controller
{
    public function __invoke(Partenaire $partenaire, LogoPartenaire $logos): BinaryFileResponse
    {
        $chemin = $logos->chemin($partenaire);

        abort_if($chemin === null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
