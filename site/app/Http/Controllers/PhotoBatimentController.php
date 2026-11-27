<?php

namespace App\Http\Controllers;

use App\Models\Batiment;
use App\Services\PhotosBatiment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert une photo d'un bâtiment (publique), ou sa miniature avec ?miniature=1.
 */
class PhotoBatimentController extends Controller
{
    public function __invoke(Request $request, Batiment $batiment, int $index, PhotosBatiment $photos): BinaryFileResponse
    {
        $chemin = $photos->chemin($batiment, $index, $request->boolean('miniature'));

        abort_if($chemin === null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
