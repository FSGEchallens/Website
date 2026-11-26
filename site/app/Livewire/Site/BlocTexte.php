<?php

namespace App\Livewire\Site;

use App\Models\BlocContenu;
use App\Support\NettoyeurHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Bloc de texte modifiable sur place par le comité et l'administrateur (petit éditeur de texte).
 * Pour les visiteurs, le bloc est affiché sans Livewire (voir partials/bloc-contenu).
 */
class BlocTexte extends Component
{
    #[Locked]
    public string $cle = '';

    /** Présentation du bloc : « texte », « encart » ou « recrute ». */
    #[Locked]
    public string $presentation = 'texte';

    public bool $enEdition = false;

    public string $titre = '';

    public string $contenu = '';

    public bool $visible = true;

    public ?string $confirmation = null;

    public function mount(string $cle, string $presentation = 'texte'): void
    {
        $this->cle = $cle;
        $this->presentation = $presentation;
    }

    public function modifier(): void
    {
        $this->authorize('gerer-contenu');

        $bloc = $this->bloc();
        $this->titre = (string) $bloc->titre;
        $this->contenu = (string) $bloc->contenu;
        $this->visible = $bloc->visible;
        $this->confirmation = null;
        $this->resetErrorBag();
        $this->enEdition = true;
    }

    public function annuler(): void
    {
        $this->enEdition = false;
        $this->resetErrorBag();
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-contenu');

        $bloc = $this->bloc();

        $this->validate([
            'titre' => [$bloc->avec_titre ? 'required' : 'nullable', 'string', 'max:150'],
            'contenu' => ['nullable', 'string', 'max:50000'],
            'visible' => ['boolean'],
        ], [
            'titre.required' => 'Le titre est obligatoire.',
            'contenu.max' => 'Le texte est trop long.',
        ]);

        $contenu = NettoyeurHtml::nettoyer($this->contenu);

        if (NettoyeurHtml::estVide($contenu)) {
            throw ValidationException::withMessages(['contenu' => 'Le texte ne peut pas être vide.'.($bloc->masquable ? ' Pour ne plus l\'afficher, décochez plutôt « Afficher sur la page d\'accueil ».' : '')]);
        }

        $bloc->fill([
            'titre' => $bloc->avec_titre ? trim($this->titre) : $bloc->titre,
            'contenu' => $contenu,
            'visible' => $bloc->masquable ? $this->visible : true,
        ])->save();

        $this->enEdition = false;
        $this->confirmation = 'Enregistré.';
    }

    /**
     * Affiche ou masque le bloc sans ouvrir l'éditeur.
     */
    public function basculerVisibilite(): void
    {
        $this->authorize('gerer-contenu');

        $bloc = $this->bloc();

        if ($bloc->masquable) {
            $bloc->update(['visible' => ! $bloc->visible]);
            $this->confirmation = $bloc->visible ? 'Le bloc est de nouveau affiché.' : 'Le bloc est masqué pour les visiteurs.';
        }
    }

    private function bloc(): BlocContenu
    {
        return BlocContenu::where('cle', $this->cle)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.site.bloc-texte', ['bloc' => $this->bloc()]);
    }
}
