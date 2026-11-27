<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Jour, heures, salles et moniteurs d'un cours ou d'une session.
 * `salles` est une liste d'identifiants de salles (une ou plusieurs).
 * `moniteurs` est une liste ordonnée d'identifiants de membres : le premier est le moniteur principal.
 */
trait AHoraire
{
    public const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function libelleJour(): ?string
    {
        return self::JOURS[$this->jour] ?? null;
    }

    /**
     * Ex. « Mardi 18:00–20:00 » (null si le jour ou les heures manquent).
     */
    public function libelleHoraire(): ?string
    {
        if ($this->libelleJour() === null || $this->heure_debut === null || $this->heure_fin === null) {
            return null;
        }

        return $this->libelleJour().' '.substr((string) $this->heure_debut, 0, 5).'–'.substr((string) $this->heure_fin, 0, 5);
    }

    /**
     * Salles utilisées (ex. Court-Champ 3 et 4 pour la salle double entière).
     *
     * @return array<int, int>
     */
    public function idsSalles(): array
    {
        return array_values(array_map('intval', $this->salles ?? []));
    }

    /**
     * @return array<int, int>
     */
    public function idsMoniteurs(): array
    {
        return array_values(array_map('intval', $this->moniteurs ?? []));
    }

    /**
     * Moniteurs dans l'ordre enregistré (le premier est le principal).
     *
     * @param  Collection<int, User>|null  $membres  membres déjà chargés (évite une requête par ligne)
     * @return Collection<int, User>
     */
    public function membresMoniteurs(?Collection $membres = null): Collection
    {
        $ids = $this->idsMoniteurs();

        if ($ids === []) {
            return collect();
        }

        $membres ??= User::whereIn('id', $ids)->get();
        $parId = $membres->keyBy('id');

        return collect($ids)->map(fn (int $id) => $parId->get($id))->filter()->values();
    }
}
