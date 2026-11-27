<?php

namespace App\Models;

use App\Models\Concerns\AHoraire;
use App\Models\Concerns\Journalise;
use Database\Factories\CoursFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cours d'un groupe, en général une fois par semaine (jour, heures, salle, moniteurs).
 * S'il a plusieurs entraînements par semaine, ils sont décrits par ses sessions.
 */
#[Fillable(['groupe_id', 'nom', 'jour', 'heure_debut', 'heure_fin', 'salle_id', 'moniteurs', 'remarque', 'actif'])]
class Cours extends Model
{
    /** @use HasFactory<CoursFactory> */
    use AHoraire, HasFactory, Journalise;

    protected $table = 'cours';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jour' => 'integer',
            'salle_id' => 'integer',
            'moniteurs' => 'array',
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Groupe, $this>
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * @return HasMany<SessionCours, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SessionCours::class)->orderBy('ordre')->orderBy('id');
    }

    /**
     * Nom affiché : le nom saisi, sinon l'horaire.
     */
    public function libelle(): string
    {
        return $this->nom ?: ($this->libelleHoraire() ?? 'Cours sans horaire');
    }

    public function libelleJournal(): string
    {
        return 'Cours « '.$this->libelle().' » ('.($this->groupe?->nom ?? 'groupe supprimé').')';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [
            'groupe_id' => 'Groupe', 'nom' => 'Nom', 'jour' => 'Jour', 'heure_debut' => 'Début', 'heure_fin' => 'Fin',
            'salle_id' => 'Salle', 'moniteurs' => 'Moniteurs', 'remarque' => 'Remarque', 'ordre' => 'Ordre', 'actif' => 'Actif',
        ];
    }

    /**
     * @return BelongsTo<Salle, $this>
     */
    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }
}
