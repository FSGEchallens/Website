<?php

namespace App\Models;

use App\Models\Concerns\AHoraire;
use App\Models\Concerns\Journalise;
use Database\Factories\SessionCoursFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session d'un cours qui a plusieurs entraînements par semaine (ex. lundi et jeudi).
 * Salle et moniteurs vides = ceux du cours.
 */
#[Fillable(['cours_id', 'jour', 'heure_debut', 'heure_fin', 'salle_id', 'moniteurs', 'remarque'])]
class SessionCours extends Model
{
    /** @use HasFactory<SessionCoursFactory> */
    use AHoraire, HasFactory, Journalise;

    protected $table = 'sessions_cours';

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
        ];
    }

    /**
     * @return BelongsTo<Cours, $this>
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function salleEffective(): ?Salle
    {
        return $this->salle ?? $this->cours?->salle;
    }

    /**
     * @return array<int, int>
     */
    public function idsMoniteursEffectifs(): array
    {
        return $this->idsMoniteurs() !== [] ? $this->idsMoniteurs() : ($this->cours?->idsMoniteurs() ?? []);
    }

    public function libelleJournal(): string
    {
        return 'Session « '.($this->libelleHoraire() ?? '?').' » du cours « '.($this->cours?->libelle() ?? '?').' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [
            'jour' => 'Jour', 'heure_debut' => 'Début', 'heure_fin' => 'Fin', 'salle_id' => 'Salle',
            'moniteurs' => 'Moniteurs', 'remarque' => 'Remarque', 'ordre' => 'Ordre',
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
