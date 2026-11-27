<?php

namespace App\Services;

use App\Models\Salle;
use App\Support\TraitementImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo d'une salle : une version 1600 px et une miniature 480 px, en JPEG sans métadonnées (ni EXIF ni GPS).
 * L'ancienne photo est archivée dans le journal avant d'être remplacée ou supprimée.
 */
class PhotosSalle
{
    private const TAILLE = 1600;

    private const TAILLE_MINIATURE = 480;

    /**
     * Traite et enregistre la photo, retire l'ancienne et renvoie le nom du nouveau fichier.
     */
    public function enregistrer(Salle $salle, string $cheminSource): string
    {
        $photo = TraitementImage::versJpeg($cheminSource, self::TAILLE, 'photo');
        $miniature = TraitementImage::versJpeg($cheminSource, self::TAILLE_MINIATURE, 'photo', 75);

        $this->supprimer($salle);

        $nom = 'salle-'.(Str::slug(Str::limit($salle->nom, 40, '')) ?: 'photo').'-'.Str::lower(Str::random(6)).'.jpg';
        $disque = Storage::disk(Salle::DISQUE);
        $disque->put(Salle::DOSSIER.'/'.$nom, $photo);
        $disque->put(Salle::DOSSIER.'/'.self::nomMiniature($nom), $miniature);

        return $nom;
    }

    public function supprimer(Salle $salle): void
    {
        if ($salle->photo === null) {
            return;
        }

        $nom = basename($salle->photo);
        $salle->archiverFichier('photo', Salle::DISQUE, Salle::DOSSIER.'/'.$nom);
        Storage::disk(Salle::DISQUE)->delete([Salle::DOSSIER.'/'.$nom, Salle::DOSSIER.'/'.self::nomMiniature($nom)]);
    }

    public function chemin(Salle $salle, bool $miniature = false): ?string
    {
        if ($salle->photo === null) {
            return null;
        }

        $disque = Storage::disk(Salle::DISQUE);
        $nom = basename($salle->photo);
        $candidats = $miniature ? [self::nomMiniature($nom), $nom] : [$nom];

        foreach ($candidats as $candidat) {
            if ($disque->exists(Salle::DOSSIER.'/'.$candidat)) {
                return $disque->path(Salle::DOSSIER.'/'.$candidat);
            }
        }

        return null;
    }

    public static function nomMiniature(string $nom): string
    {
        return preg_replace('/\.jpg$/', '_miniature.jpg', $nom) ?? $nom;
    }
}
