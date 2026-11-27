<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\GroupeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Groupe de gym (ex. « Enfantine », « Agrès kids concours ») : page publique, âge, tarif…
 * Il est composé de cours (en général un par créneau hebdomadaire).
 */
#[Fillable(['nom', 'slug', 'categorie', 'description', 'age', 'prerequis', 'concours', 'prix', 'infos_tarif', 'visible'])]
class Groupe extends Model
{
    /** @use HasFactory<GroupeFactory> */
    use HasFactory, Journalise;

    /** Catégories pour le menu « Les groupes ». */
    public const CATEGORIES = ['jeunesse' => 'Jeunesse', 'adulte' => 'Adulte'];

    public function libelleCategorie(): ?string
    {
        return self::CATEGORIES[$this->categorie] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
            'ordre' => 'integer',
            'visible' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Cours, $this>
     */
    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class)->orderBy('ordre')->orderBy('id');
    }

    /**
     * @param  Builder<Groupe>  $query
     */
    public function scopeDansLOrdre(Builder $query): void
    {
        $query->orderBy('ordre')->orderBy('id');
    }

    /**
     * Adresse de page libre tirée du nom (ex. « agres-kids-plaisir », sinon « agres-kids-plaisir-2 »…).
     */
    public static function slugLibre(string $nom): string
    {
        $base = Str::slug($nom) ?: 'groupe';
        $slug = $base;

        for ($numero = 2; static::where('slug', $slug)->exists(); $numero++) {
            $slug = $base.'-'.$numero;
        }

        return $slug;
    }

    public function libelleJournal(): string
    {
        return 'Groupe « '.$this->nom.' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [
            'nom' => 'Nom', 'slug' => 'Adresse de la page', 'categorie' => 'Catégorie', 'description' => 'Description',
            'age' => 'Âge', 'prerequis' => 'Prérequis', 'concours' => 'Concours et manifestations', 'prix' => 'Prix',
            'infos_tarif' => 'Informations du tarif', 'ordre' => 'Ordre', 'visible' => 'Affiché',
        ];
    }
}
