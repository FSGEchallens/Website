<?php

namespace App\Livewire\Site;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Page « Le comité » pour le comité et l'administrateur : mêmes cartes que pour les visiteurs,
 * avec la possibilité de monter ou descendre une personne.
 */
class OrdreComite extends Component
{
    public bool $enEdition = false;

    public function basculerEdition(): void
    {
        $this->authorize('gerer-membres');
        $this->enEdition = ! $this->enEdition;
    }

    /**
     * Monte (-1) ou descend (+1) une personne dans l'ordre d'affichage.
     */
    public function deplacer(int $id, int $sens): void
    {
        $this->authorize('gerer-membres');

        $ids = User::duComite()->pluck('id')->all();
        $position = array_search($id, $ids, true);
        $cible = $position === false ? false : $position + ($sens < 0 ? -1 : 1);

        if ($position === false || $cible < 0 || $cible >= count($ids)) {
            return;
        }

        [$ids[$position], $ids[$cible]] = [$ids[$cible], $ids[$position]];

        // Chaque changement d'ordre est inscrit au journal (champ « Ordre au comité »).
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $rang => $identifiant) {
                $membre = User::find($identifiant);

                if ($membre !== null && $membre->comite_ordre !== $rang + 1) {
                    $membre->forceFill(['comite_ordre' => $rang + 1])->save();
                }
            }
        });
    }

    public function render(): View
    {
        return view('livewire.site.ordre-comite', ['membres' => User::duComite()->get()]);
    }
}
