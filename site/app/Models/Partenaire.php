<?php

namespace App\Models;

use Database\Factories\PartenaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Partenaire affiché sur la page d'accueil.
 *
 * `logo` : « images/partenaires/… » pour les logos d'origine (dossier public),
 * sinon nom du fichier importé dans le stockage privé (servi par LogoPartenaireController).
 */
#[Fillable(['nom', 'url', 'visible'])]
class Partenaire extends Model
{
    /** @use HasFactory<PartenaireFactory> */
    use HasFactory;

    public const DOSSIER_ORIGINE = 'images/partenaires/';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    /**
     * @param  Builder<Partenaire>  $query
     */
    public function scopeDansLOrdre(Builder $query): void
    {
        $query->orderBy('ordre')->orderBy('id');
    }

    /**
     * @param  Builder<Partenaire>  $query
     */
    public function scopeVisibles(Builder $query): void
    {
        $query->where('visible', true);
    }

    public function aUnLogoDOrigine(): bool
    {
        return $this->logo !== null && str_starts_with($this->logo, self::DOSSIER_ORIGINE);
    }

    public function urlLogo(): ?string
    {
        if ($this->logo === null) {
            return null;
        }

        if ($this->aUnLogoDOrigine()) {
            return asset($this->logo);
        }

        return route('partenaires.logo', ['partenaire' => $this, 'v' => substr(md5((string) $this->updated_at), 0, 8)]);
    }
}
