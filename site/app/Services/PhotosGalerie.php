<?php

namespace App\Services;

use App\Models\Album;
use App\Models\PhotoGalerie;
use App\Support\TraitementImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ajout des photos de la galerie : grande version 1920 px et miniature 480 px,
 * en JPEG (métadonnées EXIF/GPS supprimées), stockées hors du dossier public.
 */
class PhotosGalerie
{
    private const TAILLE = 1920;

    private const TAILLE_MINIATURE = 480;

    public function ajouter(Album $album, string $cheminSource, ?string $nomOriginal = null): PhotoGalerie
    {
        $grande = TraitementImage::versJpeg($cheminSource, self::TAILLE, 'photoEnvoyee');
        $miniature = TraitementImage::versJpeg($cheminSource, self::TAILLE_MINIATURE, 'photoEnvoyee', 72);
        $dimensions = @getimagesizefromstring($grande) ?: [null, null];

        $photo = new PhotoGalerie([
            'album_id' => $album->id,
            'fichier' => 'photo-'.Str::lower(Str::random(16)).'.jpg',
            'nom_original' => $nomOriginal !== null ? Str::limit(basename($nomOriginal), 200, '') : null,
            'largeur' => $dimensions[0],
            'hauteur' => $dimensions[1],
            'ordre' => (int) PhotoGalerie::withTrashed()->where('album_id', $album->id)->max('ordre') + 1,
        ]);

        $disque = Storage::disk(PhotoGalerie::DISQUE);
        $disque->put($photo->chemin(), $grande);
        $disque->put($photo->chemin(miniature: true), $miniature);

        $photo->save();

        return $photo;
    }
}
