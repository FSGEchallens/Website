<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Album de la galerie photo. Supprimé = mis à la corbeille (avec ses photos) ;
 * effacé définitivement après la durée de conservation du journal.
 */
#[Fillable(['titre', 'date', 'description', 'visible'])]
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory, Journalise, Prunable, SoftDeletes;

    public const PHOTOS_MAX = 999;

    protected static function booted(): void
    {
        // En restaurant un album, on restaure aussi les photos mises à la corbeille en même temps que lui.
        static::restoring(function (Album $album): void {
            $supprimeLe = $album->getOriginal('deleted_at');

            if ($supprimeLe !== null) {
                PhotoGalerie::onlyTrashed()
                    ->where('album_id', $album->id)
                    ->whereBetween('deleted_at', [$album->asDateTime($supprimeLe)->subMinute(), $album->asDateTime($supprimeLe)->addMinute()])
                    ->update(['deleted_at' => null]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'visible' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<PhotoGalerie, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PhotoGalerie::class)->orderBy('ordre')->orderBy('id');
    }

    /**
     * @return BelongsTo<PhotoGalerie, $this>
     */
    public function couverture(): BelongsTo
    {
        return $this->belongsTo(PhotoGalerie::class, 'couverture_id');
    }

    /**
     * Photo de couverture choisie, sinon la première photo de l'album.
     */
    public function photoDeCouverture(): ?PhotoGalerie
    {
        return $this->couverture ?? $this->photos()->first();
    }

    /**
     * @param  Builder<Album>  $query
     */
    public function scopeDuPlusRecent(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('id');
    }

    /**
     * À l'intérieur du modèle, $this->visible est une propriété interne de Laravel : on lit la colonne.
     */
    public function estPublie(): bool
    {
        return (bool) $this->getAttribute('visible');
    }

    public static function slugUnique(string $titre, ?int $saufId = null): string
    {
        $base = Str::slug($titre) ?: 'album';
        $slug = $base;
        $numero = 2;

        while (static::withTrashed()->where('slug', $slug)->when($saufId, fn ($q) => $q->whereKeyNot($saufId))->exists()) {
            $slug = $base.'-'.$numero++;
        }

        return $slug;
    }

    /**
     * Albums dans la corbeille depuis plus longtemps que la durée de conservation : effacés définitivement.
     *
     * @return Builder<Album>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<', now()->subDays((int) config('journal.conservation_jours')));
    }

    protected function pruning(): void
    {
        foreach (PhotoGalerie::withTrashed()->where('album_id', $this->id)->get() as $photo) {
            $photo->supprimerFichiers();
        }
    }

    public function libelleJournal(): string
    {
        return 'Album « '.$this->titre.' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [
            'titre' => 'Titre', 'slug' => 'Adresse', 'date' => 'Date', 'description' => 'Description',
            'couverture_id' => 'Photo de couverture', 'visible' => 'Publié', 'deleted_at' => 'Mis à la corbeille le',
        ];
    }
}
