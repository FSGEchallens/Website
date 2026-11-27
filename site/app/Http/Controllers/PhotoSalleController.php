<?php

namespace App\Http\Controllers;

use App\Models\Salle;
use App\Services\PhotosSalle;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert la photo d'une salle (publique), ou sa miniature avec ?miniature=1.
 */
class PhotoSalleController extends Controller
{
    public function __invoke(Request $request, Salle $salle, PhotosSalle $photos): BinaryFileResponse
    {
        $chemin = $photos->chemin($salle, $request->boolean('miniature'));

        abort_if($chemin === null, 404);

        return response()->file($chemin, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
