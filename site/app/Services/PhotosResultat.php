<?php

namespace App\Services;

use App\Models\Resultat;
use App\Support\TraitementImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos des résultats : une version 1600 px et une miniature 500 px, en JPEG sans métadonnées,
 * stockées hors du dossier public. Les photos retirées sont archivées dans le journal.
 */
class PhotosResultat
{
    private const TAILLE = 1600;

    private const TAILLE_MINIATURE = 500;

    /**
     * Enregistre une photo (depuis un fichier local) et renvoie son nom.
     */
    public function enregistrer(Resultat $resultat, string $cheminSource): string
    {
        $nom = 'resultat-'.$resultat->getKey().'-'.(Str::slug(Str::limit($resultat->titre, 40, '')) ?: 'photo').'-'.Str::lower(Str::random(6)).'.jpg';
        $disque = Storage::disk(Resultat::DISQUE);

        $disque->put(Resultat::DOSSIER_PHOTOS.'/'.$nom, TraitementImage::versJpeg($cheminSource, self::TAILLE, 'nouvellesPhotos'));
        $disque->put(Resultat::DOSSIER_PHOTOS.'/'.self::nomMiniature($nom), TraitementImage::versJpeg($cheminSource, self::TAILLE_MINIATURE, 'nouvellesPhotos', 75));

        return $nom;
    }

    public function supprimer(Resultat $resultat, string $nom): void
    {
        $nom = basename($nom);
        $disque = Storage::disk(Resultat::DISQUE);

        $resultat->archiverFichier('photos', Resultat::DISQUE, Resultat::DOSSIER_PHOTOS.'/'.$nom);
        $disque->delete([Resultat::DOSSIER_PHOTOS.'/'.$nom, Resultat::DOSSIER_PHOTOS.'/'.self::nomMiniature($nom)]);
    }

    public function supprimerTout(Resultat $resultat): void
    {
        foreach ($resultat->photos ?? [] as $nom) {
            $this->supprimer($resultat, $nom);
        }
    }

    /**
     * Chemin de la photo (ou de sa miniature ; à défaut de miniature, la photo elle-même).
     */
    public function chemin(Resultat $resultat, int $index, bool $miniature): ?string
    {
        $nom = $resultat->photos[$index] ?? null;

        if ($nom === null) {
            return null;
        }

        $disque = Storage::disk(Resultat::DISQUE);
        $candidats = $miniature ? [self::nomMiniature(basename($nom)), basename($nom)] : [basename($nom)];

        foreach ($candidats as $candidat) {
            if ($disque->exists(Resultat::DOSSIER_PHOTOS.'/'.$candidat)) {
                return $disque->path(Resultat::DOSSIER_PHOTOS.'/'.$candidat);
            }
        }

        return null;
    }

    public static function nomMiniature(string $nom): string
    {
        return preg_replace('/\.jpg$/', '_miniature.jpg', $nom) ?? $nom;
    }
}
