<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\SalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Salle d'un bâtiment (ex. « Salle 3 » du bâtiment « Echallens, Court-Champ »).
 * Un cours ou une session peut utiliser plusieurs salles, toujours du même bâtiment.
 */
#[Fillable(['batiment_id', 'nom'])]
class Salle extends Model
{
    /** @use HasFactory<SalleFactory> */
    use HasFactory, Journalise;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['batiment_id' => 'integer', 'ordre' => 'integer'];
    }

    /**
     * @return BelongsTo<Batiment, $this>
     */
    public function batiment(): BelongsTo
    {
        return $this->belongsTo(Batiment::class);
    }

    /**
     * Ex. « Echallens, Court-Champ – Salle 3 ».
     */
    public function libelleComplet(): string
    {
        return ($this->batiment?->nom ? $this->batiment->nom.' – ' : '').$this->nom;
    }

    /**
     * Libellé de plusieurs salles d'un même bâtiment : « Echallens, Court-Champ – Salle 3 + Salle 4 ».
     *
     * @param  array<int, int>  $ids
     * @param  Collection<int, Salle>  $salles  salles déjà chargées (avec leur bâtiment)
     */
    public static function libellePour(array $ids, Collection $salles): ?string
    {
        $choisies = collect($ids)->map(fn (int $id) => $salles->firstWhere('id', $id))->filter();

        if ($choisies->isEmpty()) {
            return null;
        }

        return $choisies->groupBy('batiment_id')
            ->map(fn (Collection $groupe) => ($groupe->first()->batiment?->nom ? $groupe->first()->batiment->nom.' – ' : '').$groupe->pluck('nom')->implode(' + '))
            ->implode(' ; ');
    }

    /**
     * Nombre de cours et de sessions qui utilisent cette salle.
     *
     * @return array{cours: int, sessions: int}
     */
    public function utilisations(): array
    {
        $utilise = fn (array $salles) => in_array($this->id, array_map('intval', $salles), true);

        return [
            'cours' => Cours::whereNotNull('salles')->pluck('salles')->filter($utilise)->count(),
            'sessions' => SessionCours::whereNotNull('salles')->pluck('salles')->filter($utilise)->count(),
        ];
    }

    public function libelleJournal(): string
    {
        return 'Salle « '.$this->libelleComplet().' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return ['batiment_id' => 'Bâtiment', 'nom' => 'Nom', 'ordre' => 'Ordre'];
    }
}
