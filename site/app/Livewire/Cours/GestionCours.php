<?php

namespace App\Livewire\Cours;

use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Salle;
use App\Models\SessionCours;
use App\Models\User;
use App\Support\NettoyeurHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gestion des groupes, cours et sessions (comité et administrateur), sous forme d'arbre dépliable :
 * groupe → cours → sessions.
 */
#[Title('Groupes')]
class GestionCours extends Component
{
    /** Élément en cours de modification : « groupe », « cours » ou « session » (null = pas de fenêtre ouverte). */
    #[Locked]
    public ?string $type = null;

    /** Identifiant de l'élément modifié (0 = nouveau). */
    #[Locked]
    public int $editionId = 0;

    /** Groupe (pour un nouveau cours) ou cours (pour une nouvelle session) parent. */
    #[Locked]
    public ?int $parentId = null;

    /** @var array<string, mixed> */
    public array $donnees = [];

    public ?int $moniteurAAjouter = null;

    public ?int $salleAAjouter = null;

    public ?string $confirmation = null;

    public function mount(): void
    {
        $this->authorize('gerer-cours');
    }

    public function nouveauGroupe(): void
    {
        $this->ouvrir('groupe', 0, null, [
            'nom' => '', 'categorie' => '', 'description' => '', 'age' => '', 'prerequis' => '',
            'concours' => '', 'prix' => '', 'infos_tarif' => '', 'visible' => true,
        ]);
    }

    public function modifierGroupe(int $id): void
    {
        $groupe = Groupe::findOrFail($id);

        $this->ouvrir('groupe', $groupe->id, null, [
            'nom' => $groupe->nom, 'categorie' => (string) $groupe->categorie,
            'description' => (string) $groupe->description, 'age' => (string) $groupe->age,
            'prerequis' => (string) $groupe->prerequis, 'concours' => (string) $groupe->concours,
            'prix' => $groupe->prix === null ? '' : (string) (float) $groupe->prix,
            'infos_tarif' => (string) $groupe->infos_tarif, 'visible' => $groupe->visible,
        ]);
    }

    public function nouveauCours(int $groupeId): void
    {
        $groupe = Groupe::findOrFail($groupeId);

        $this->ouvrir('cours', 0, $groupe->id, [
            'nom' => '', 'jour' => '', 'heure_debut' => '', 'heure_fin' => '', 'salles' => [],
            'moniteurs' => [], 'remarque' => '', 'actif' => true,
        ]);
    }

    public function modifierCours(int $id): void
    {
        $cours = Cours::findOrFail($id);

        $this->ouvrir('cours', $cours->id, $cours->groupe_id, [
            'nom' => (string) $cours->nom, 'jour' => (string) ($cours->jour ?? ''),
            'heure_debut' => substr((string) $cours->heure_debut, 0, 5), 'heure_fin' => substr((string) $cours->heure_fin, 0, 5),
            'salles' => $cours->idsSalles(), 'moniteurs' => $cours->idsMoniteurs(),
            'remarque' => (string) $cours->remarque, 'actif' => $cours->actif,
        ]);
    }

    public function nouvelleSession(int $coursId): void
    {
        $cours = Cours::findOrFail($coursId);

        $this->ouvrir('session', 0, $cours->id, [
            'jour' => '', 'heure_debut' => '', 'heure_fin' => '', 'salles' => [], 'moniteurs' => [], 'remarque' => '',
        ]);
    }

    public function modifierSession(int $id): void
    {
        $session = SessionCours::findOrFail($id);

        $this->ouvrir('session', $session->id, $session->cours_id, [
            'jour' => (string) $session->jour,
            'heure_debut' => substr((string) $session->heure_debut, 0, 5), 'heure_fin' => substr((string) $session->heure_fin, 0, 5),
            'salles' => $session->idsSalles(), 'moniteurs' => $session->idsMoniteurs(), 'remarque' => (string) $session->remarque,
        ]);
    }

    public function fermer(): void
    {
        $this->reset('type', 'editionId', 'parentId', 'donnees', 'moniteurAAjouter', 'salleAAjouter');
        $this->resetValidation();
    }

    public function ajouterMoniteur(): void
    {
        $id = (int) $this->moniteurAAjouter;
        $moniteurs = $this->donnees['moniteurs'] ?? [];

        if ($id > 0 && ! in_array($id, $moniteurs, true) && $this->moniteursPossibles()->contains('id', $id)) {
            $moniteurs[] = $id;
        }

        $this->donnees['moniteurs'] = array_values($moniteurs);
        $this->moniteurAAjouter = null;
    }

    public function ajouterSalle(): void
    {
        $id = (int) $this->salleAAjouter;
        $salles = $this->donnees['salles'] ?? [];

        $salle = $id > 0 ? Salle::find($id) : null;
        $this->resetValidation('donnees.salles');

        if ($salle !== null && ! in_array($id, $salles, true)) {
            // Plusieurs salles seulement dans un même bâtiment.
            if ($salles !== [] && Salle::whereKey($salles)->where('batiment_id', '!=', $salle->batiment_id)->exists()) {
                $this->addError('donnees.salles', 'Les salles d’un cours doivent être dans le même bâtiment.');

                return;
            }

            $salles[] = $id;
        }

        $this->donnees['salles'] = array_values($salles);
        $this->salleAAjouter = null;
    }

    public function retirerSalle(int $index): void
    {
        $salles = $this->donnees['salles'] ?? [];
        unset($salles[$index]);
        $this->donnees['salles'] = array_values($salles);
    }

    public function retirerMoniteur(int $index): void
    {
        $moniteurs = $this->donnees['moniteurs'] ?? [];
        unset($moniteurs[$index]);
        $this->donnees['moniteurs'] = array_values($moniteurs);
    }

    /**
     * Met un moniteur en premier (moniteur principal).
     */
    public function rendrePrincipal(int $index): void
    {
        $moniteurs = $this->donnees['moniteurs'] ?? [];

        if (isset($moniteurs[$index])) {
            $id = $moniteurs[$index];
            unset($moniteurs[$index]);
            array_unshift($moniteurs, $id);
        }

        $this->donnees['moniteurs'] = array_values($moniteurs);
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-cours');

        match ($this->type) {
            'groupe' => $this->enregistrerGroupe(),
            'cours' => $this->enregistrerCours(),
            'session' => $this->enregistrerSession(),
            default => null,
        };
    }

    /**
     * Monte (-1) ou descend (+1) un groupe, un cours ou une session parmi ses voisins.
     */
    public function deplacer(string $type, int $id, int $sens): void
    {
        $this->authorize('gerer-cours');

        $voisins = match ($type) {
            'groupe' => Groupe::dansLOrdre()->get(),
            'cours' => Cours::where('groupe_id', Cours::findOrFail($id)->groupe_id)->orderBy('ordre')->orderBy('id')->get(),
            'session' => SessionCours::where('cours_id', SessionCours::findOrFail($id)->cours_id)->orderBy('ordre')->orderBy('id')->get(),
            default => abort(404),
        };

        $ids = $voisins->pluck('id')->all();
        $position = array_search($id, $ids, true);
        $cible = $position === false ? false : $position + ($sens < 0 ? -1 : 1);

        if ($position === false || $cible < 0 || $cible >= count($ids)) {
            return;
        }

        [$ids[$position], $ids[$cible]] = [$ids[$cible], $ids[$position]];
        $parId = $voisins->keyBy('id');

        DB::transaction(function () use ($ids, $parId): void {
            foreach ($ids as $rang => $identifiant) {
                $element = $parId[$identifiant];

                if ($element->ordre !== $rang + 1) {
                    $element->forceFill(['ordre' => $rang + 1])->save();
                }
            }
        });
    }

    public function supprimer(string $type, int $id): void
    {
        $this->authorize('gerer-cours');
        $this->confirmation = null;

        if ($type === 'groupe') {
            $groupe = Groupe::withCount('cours')->findOrFail($id);

            if ($groupe->cours_count > 0) {
                $this->confirmation = 'Supprimez d’abord les cours du groupe « '.$groupe->nom.' ».';

                return;
            }

            $groupe->delete();
            $this->confirmation = 'Le groupe « '.$groupe->nom.' » a été supprimé (il peut être restauré depuis le journal).';
        } elseif ($type === 'cours') {
            $cours = Cours::withCount('sessions')->findOrFail($id);

            if ($cours->sessions_count > 0) {
                $this->confirmation = 'Supprimez d’abord les sessions du cours « '.$cours->libelle().' ».';

                return;
            }

            $cours->delete();
            $this->confirmation = 'Le cours « '.$cours->libelle().' » a été supprimé (il peut être restauré depuis le journal).';
        } elseif ($type === 'session') {
            $session = SessionCours::findOrFail($id);
            $session->delete();
            $this->confirmation = 'La session « '.$session->libelleHoraire().' » a été supprimée (elle peut être restaurée depuis le journal).';
        }
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'donnees.nom' => 'nom', 'donnees.categorie' => 'catégorie',
            'donnees.age' => 'âge', 'donnees.prerequis' => 'prérequis', 'donnees.concours' => 'concours',
            'donnees.prix' => 'prix', 'donnees.infos_tarif' => 'informations du tarif', 'donnees.jour' => 'jour',
            'donnees.heure_debut' => 'heure de début', 'donnees.heure_fin' => 'heure de fin', 'donnees.salles' => 'salles', 'donnees.salles.*' => 'salle',
            'donnees.remarque' => 'remarque', 'donnees.description' => 'description',
        ];
    }

    private function enregistrerGroupe(): void
    {
        $this->validate([
            'donnees.nom' => ['required', 'string', 'max:150'],
            'donnees.categorie' => ['nullable', 'string', 'max:100'],
            'donnees.description' => ['nullable', 'string', 'max:50000'],
            'donnees.age' => ['nullable', 'string', 'max:150'],
            'donnees.prerequis' => ['nullable', 'string', 'max:150'],
            'donnees.concours' => ['nullable', 'string', 'max:2000'],
            'donnees.prix' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'donnees.infos_tarif' => ['nullable', 'string', 'max:2000'],
            'donnees.visible' => ['boolean'],
        ]);

        $d = $this->donnees;
        $groupe = $this->editionId ? Groupe::findOrFail($this->editionId) : new Groupe;
        $description = (string) ($d['description'] ?? '');

        $groupe->fill([
            'nom' => trim($d['nom']),
            'categorie' => $this->texteOuNull($d['categorie'] ?? null),
            'description' => NettoyeurHtml::estVide($description) ? null : NettoyeurHtml::nettoyer($description),
            'age' => $this->texteOuNull($d['age'] ?? null),
            'prerequis' => $this->texteOuNull($d['prerequis'] ?? null),
            'concours' => $this->texteOuNull($d['concours'] ?? null),
            'prix' => ($d['prix'] ?? '') === '' ? null : $d['prix'],
            'infos_tarif' => $this->texteOuNull($d['infos_tarif'] ?? null),
            'visible' => (bool) ($d['visible'] ?? true),
        ]);

        if (! $groupe->exists) {
            // Adresse de la page créée à partir du nom, puis gardée telle quelle (les liens ne cassent pas si le nom change).
            $groupe->slug = Groupe::slugLibre($groupe->nom);
            $groupe->forceFill(['ordre' => (int) Groupe::max('ordre') + 1]);
        }

        $groupe->save();
        $this->terminer('Le groupe « '.$groupe->nom.' » a été enregistré.');
    }

    private function enregistrerCours(): void
    {
        $this->validate($this->reglesHoraire(obligatoire: false) + [
            'donnees.nom' => ['nullable', 'string', 'max:150'],
            'donnees.actif' => ['boolean'],
        ]);

        $d = $this->donnees;
        $cours = $this->editionId ? Cours::findOrFail($this->editionId) : new Cours(['groupe_id' => Groupe::findOrFail($this->parentId)->id]);

        $cours->fill($this->valeursHoraire($d) + [
            'nom' => $this->texteOuNull($d['nom'] ?? null),
            'actif' => (bool) ($d['actif'] ?? true),
        ]);

        if (! $cours->exists) {
            $cours->forceFill(['ordre' => (int) Cours::where('groupe_id', $cours->groupe_id)->max('ordre') + 1]);
        }

        $cours->save();
        $this->terminer('Le cours « '.$cours->libelle().' » a été enregistré.');
    }

    private function enregistrerSession(): void
    {
        $this->validate($this->reglesHoraire(obligatoire: true));

        $session = $this->editionId ? SessionCours::findOrFail($this->editionId) : new SessionCours(['cours_id' => Cours::findOrFail($this->parentId)->id]);
        $session->fill($this->valeursHoraire($this->donnees));

        if (! $session->exists) {
            $session->forceFill(['ordre' => (int) SessionCours::where('cours_id', $session->cours_id)->max('ordre') + 1]);
        }

        $session->save();
        $this->terminer('La session « '.$session->libelleHoraire().' » a été enregistrée.');
    }

    /**
     * Jour et heures : obligatoires pour une session ; pour un cours, tout ou rien (un cours avec sessions n'en a pas).
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglesHoraire(bool $obligatoire): array
    {
        $requis = $obligatoire ? 'required' : 'required_with:donnees.jour,donnees.heure_debut,donnees.heure_fin';

        return [
            'donnees.jour' => [$requis, 'nullable', 'integer', 'between:1,7'],
            'donnees.heure_debut' => [$requis, 'nullable', 'date_format:H:i'],
            'donnees.heure_fin' => [$requis, 'nullable', 'date_format:H:i', 'after:donnees.heure_debut'],
            'donnees.salles' => ['array', 'max:6', function (string $attribut, mixed $valeur, \Closure $echec): void {
                if (is_array($valeur) && Salle::whereKey($valeur)->distinct()->count('batiment_id') > 1) {
                    $echec('Les salles d’un cours doivent être dans le même bâtiment.');
                }
            }],
            'donnees.salles.*' => ['integer', 'distinct', 'exists:salles,id'],
            'donnees.moniteurs' => ['array', 'max:10'],
            'donnees.moniteurs.*' => ['integer', 'distinct', 'exists:users,id'],
            'donnees.remarque' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function valeursHoraire(array $d): array
    {
        return [
            'jour' => ($d['jour'] ?? '') === '' ? null : (int) $d['jour'],
            'heure_debut' => $this->texteOuNull($d['heure_debut'] ?? null),
            'heure_fin' => $this->texteOuNull($d['heure_fin'] ?? null),
            'salles' => array_values(array_map('intval', $d['salles'] ?? [])),
            'moniteurs' => array_values(array_map('intval', $d['moniteurs'] ?? [])),
            'remarque' => $this->texteOuNull($d['remarque'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function ouvrir(string $type, int $id, ?int $parentId, array $donnees): void
    {
        $this->authorize('gerer-cours');
        $this->resetValidation();
        $this->confirmation = null;
        $this->type = $type;
        $this->editionId = $id;
        $this->parentId = $parentId;
        $this->donnees = $donnees;
        $this->moniteurAAjouter = null;
    }

    private function terminer(string $message): void
    {
        $this->fermer();
        $this->confirmation = $message;
    }

    private function texteOuNull(mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    /**
     * Membres proposés comme moniteurs d'un cours (tous les membres, les anciens membres exceptés).
     *
     * @return Collection<int, User>
     */
    private function moniteursPossibles(): Collection
    {
        return User::where(fn ($q) => $q->whereNull('date_sortie')->orWhere('date_sortie', '>', now()))
            ->orderBy('nom')->orderBy('prenom')->get();
    }

    public function render(): View
    {
        $groupes = Groupe::dansLOrdre()->with(['cours.sessions'])->get();

        $idsMoniteurs = $groupes->flatMap(fn (Groupe $g) => $g->cours->flatMap(
            fn (Cours $c) => array_merge($c->idsMoniteurs(), $c->sessions->flatMap(fn (SessionCours $s) => $s->idsMoniteurs())->all())
        ))->merge($this->donnees['moniteurs'] ?? [])->unique()->all();

        $parent = match ($this->type) {
            'cours' => Groupe::find($this->parentId),
            'session' => Cours::find($this->parentId),
            default => null,
        };

        return view('livewire.cours.gestion-cours', [
            'groupes' => $groupes,
            'membres' => User::whereIn('id', $idsMoniteurs)->get(),
            'moniteursPossibles' => $this->moniteursPossibles(),
            'categories' => Groupe::whereNotNull('categorie')->distinct()->orderBy('categorie')->pluck('categorie'),
            'salles' => Salle::with('batiment')->get()->sortBy(fn (Salle $s) => [$s->batiment?->nom, $s->ordre, $s->nom])->values(),
            'parent' => $parent instanceof Model ? $parent : null,
        ]);
    }
}
