<?php

namespace App\Http\Controllers;

use App\Models\PhotoGalerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert une photo de la galerie (ou sa miniature).
 * Une photo d'un album non publié n'est visible que par ceux qui gèrent la galerie.
 */
class PhotoGalerieController extends Controller
{
    public function __invoke(Request $request, int $photo): BinaryFileResponse
    {
        $photo = PhotoGalerie::findOrFail($photo);
        $album = $photo->album;

        abort_if($album === null || $album->trashed(), 404);
        abort_unless($album->estPublie() || ($request->user()?->can('gerer-galerie') ?? false), 404);

        $disque = Storage::disk(PhotoGalerie::DISQUE);
        $chemin = $photo->chemin($request->query('taille') === 'miniature');

        if (! $disque->exists($chemin)) {
            $chemin = $photo->chemin();
        }

        abort_unless($disque->exists($chemin), 404);

        return response()->file($disque->path($chemin), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $album->estPublie() ? 'public, max-age=604800' : 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
