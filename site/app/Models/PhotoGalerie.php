<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\PhotoGalerieFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Photo d'un album de la galerie (grande version 1920 px + miniature 480 px, en JPEG sans métadonnées).
 */
class PhotoGalerie extends Model
{
    /** @use HasFactory<PhotoGalerieFactory> */
    use HasFactory, Journalise, Prunable, SoftDeletes;

    public const DISQUE = 'local';

    public const DOSSIER = 'galerie';

    protected $table = 'photos_galerie';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class)->withTrashed();
    }

    public function chemin(bool $miniature = false): string
    {
        $nom = $miniature ? preg_replace('/\.jpg$/', '_miniature.jpg', $this->fichier) : $this->fichier;

        return self::DOSSIER.'/'.$this->album_id.'/'.basename((string) $nom);
    }

    public function url(bool $miniature = false): string
    {
        return route('galerie.photo', ['photo' => $this->id, 'taille' => $miniature ? 'miniature' : null]);
    }

    public function supprimerFichiers(): void
    {
        Storage::disk(self::DISQUE)->delete([$this->chemin(), $this->chemin(miniature: true)]);
    }

    /**
     * Photos dans la corbeille depuis plus longtemps que la durée de conservation : effacées définitivement.
     *
     * @return Builder<PhotoGalerie>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<', now()->subDays((int) config('journal.conservation_jours')));
    }

    protected function pruning(): void
    {
        $this->supprimerFichiers();
    }

    public function libelleJournal(): string
    {
        return 'Photo « '.($this->nom_original ?: $this->fichier).' » (album « '.($this->album?->titre ?? '?').' »)';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return ['fichier' => 'Fichier', 'nom_original' => 'Nom d\'origine', 'ordre' => 'Ordre', 'deleted_at' => 'Mise à la corbeille le'];
    }
}
