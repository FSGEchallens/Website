<?php

namespace App\Services;

use App\Models\Batiment;
use App\Support\TraitementImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos d'un bâtiment (3 au maximum) : une version 1600 px et une miniature 480 px, en JPEG sans métadonnées
 * (ni EXIF ni GPS). Une photo retirée est archivée dans le journal avant d'être supprimée.
 */
class PhotosBatiment
{
    private const TAILLE = 1600;

    private const TAILLE_MINIATURE = 480;

    /**
     * Traite et enregistre une photo, et renvoie le nom du fichier.
     */
    public function enregistrer(Batiment $batiment, string $cheminSource): string
    {
        $photo = TraitementImage::versJpeg($cheminSource, self::TAILLE, 'nouvellesPhotos');
        $miniature = TraitementImage::versJpeg($cheminSource, self::TAILLE_MINIATURE, 'nouvellesPhotos', 75);

        $nom = 'batiment-'.(Str::slug(Str::limit($batiment->nom, 40, '')) ?: 'photo').'-'.Str::lower(Str::random(6)).'.jpg';
        $disque = Storage::disk(Batiment::DISQUE);
        $disque->put(Batiment::DOSSIER.'/'.$nom, $photo);
        $disque->put(Batiment::DOSSIER.'/'.self::nomMiniature($nom), $miniature);

        return $nom;
    }

    public function supprimer(Batiment $batiment, string $nom): void
    {
        $nom = basename($nom);
        $batiment->archiverFichier('photos', Batiment::DISQUE, Batiment::DOSSIER.'/'.$nom);
        Storage::disk(Batiment::DISQUE)->delete([Batiment::DOSSIER.'/'.$nom, Batiment::DOSSIER.'/'.self::nomMiniature($nom)]);
    }

    public function supprimerTout(Batiment $batiment): void
    {
        foreach ($batiment->photos ?? [] as $nom) {
            $this->supprimer($batiment, $nom);
        }
    }

    /**
     * Chemin de la photo (ou de sa miniature ; à défaut de miniature, la photo elle-même).
     */
    public function chemin(Batiment $batiment, int $index, bool $miniature = false): ?string
    {
        $nom = $batiment->photos[$index] ?? null;

        if ($nom === null) {
            return null;
        }

        $disque = Storage::disk(Batiment::DISQUE);
        $nom = basename($nom);
        $candidats = $miniature ? [self::nomMiniature($nom), $nom] : [$nom];

        foreach ($candidats as $candidat) {
            if ($disque->exists(Batiment::DOSSIER.'/'.$candidat)) {
                return $disque->path(Batiment::DOSSIER.'/'.$candidat);
            }
        }

        return null;
    }

    public static function nomMiniature(string $nom): string
    {
        return preg_replace('/\.jpg$/', '_miniature.jpg', $nom) ?? $nom;
    }
}
