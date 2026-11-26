<?php

namespace App\Livewire\Site;

use App\Models\Partenaire;
use App\Services\LogoPartenaire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Gestion des partenaires de la page d'accueil (comité et administrateur).
 */
#[Title('Partenaires')]
class GestionPartenaires extends Component
{
    use WithFileUploads;

    /** null = pas de formulaire ouvert ; 0 = nouveau partenaire ; sinon identifiant du partenaire modifié. */
    #[Locked]
    public ?int $edition = null;

    public string $nom = '';

    public string $url = '';

    public bool $visible = true;

    public ?TemporaryUploadedFile $logo = null;

    public function mount(): void
    {
        $this->authorize('gerer-contenu');
    }

    public function ajouter(): void
    {
        $this->authorize('gerer-contenu');
        $this->viderFormulaire();
        $this->edition = 0;
    }

    public function modifier(int $id): void
    {
        $this->authorize('gerer-contenu');
        $partenaire = Partenaire::findOrFail($id);

        $this->viderFormulaire();
        $this->edition = $partenaire->id;
        $this->nom = $partenaire->nom;
        $this->url = (string) $partenaire->url;
        $this->visible = $partenaire->visible;
    }

    public function annuler(): void
    {
        $this->viderFormulaire();
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'visible' => ['boolean'],
            'logo' => [$this->edition === 0 ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'url.url' => 'Le lien doit être une adresse web complète, commençant par https:// ou http://.',
            'logo.required' => 'Choisissez le logo du partenaire.',
            'logo.image' => 'Le fichier choisi n\'est pas une image.',
            'logo.mimes' => 'Le logo doit être au format PNG, JPEG, WebP ou GIF.',
            'logo.max' => 'Le logo ne doit pas dépasser 5 Mo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['nom' => 'nom', 'url' => 'lien', 'logo' => 'logo'];
    }

    public function enregistrer(LogoPartenaire $logos): void
    {
        $this->authorize('gerer-contenu');

        if ($this->edition === null) {
            return;
        }

        $donnees = $this->validate();
        $estNouveau = $this->edition === 0;

        $partenaire = $estNouveau ? new Partenaire : Partenaire::findOrFail($this->edition);
        $partenaire->fill([
            'nom' => trim($donnees['nom']),
            'url' => filled($donnees['url']) ? trim($donnees['url']) : null,
            'visible' => $donnees['visible'],
        ]);

        if ($estNouveau) {
            $partenaire->ordre = (int) Partenaire::max('ordre') + 1;
        }

        $partenaire->save();

        if ($this->logo !== null) {
            $partenaire->logo = $logos->enregistrer($partenaire, $this->logo);
            $partenaire->save();
        }

        session()->flash('message', $estNouveau
            ? 'Le partenaire « '.$partenaire->nom.' » a été ajouté.'
            : 'Le partenaire « '.$partenaire->nom.' » a été enregistré.');

        $this->redirectRoute('partenaires.index', navigate: false);
    }

    public function basculerVisibilite(int $id): void
    {
        $this->authorize('gerer-contenu');
        $partenaire = Partenaire::findOrFail($id);
        $partenaire->update(['visible' => ! $partenaire->visible]);
    }

    public function supprimer(int $id, LogoPartenaire $logos): void
    {
        $this->authorize('gerer-contenu');
        $partenaire = Partenaire::findOrFail($id);

        $logos->supprimer($partenaire);
        $partenaire->delete();
        $this->renumeroter();

        if ($this->edition === $id) {
            $this->viderFormulaire();
        }

        session()->flash('message', 'Le partenaire « '.$partenaire->nom.' » a été supprimé.');
    }

    /**
     * Monte (-1) ou descend (+1) un partenaire dans la liste.
     */
    public function deplacer(int $id, int $sens): void
    {
        $this->authorize('gerer-contenu');
        $this->renumeroter();

        $ids = Partenaire::dansLOrdre()->pluck('id')->all();
        $position = array_search($id, $ids, true);
        $cible = $position === false ? false : $position + ($sens < 0 ? -1 : 1);

        if ($position === false || $cible < 0 || $cible >= count($ids)) {
            return;
        }

        [$ids[$position], $ids[$cible]] = [$ids[$cible], $ids[$position]];
        $this->enregistrerOrdre($ids);
    }

    private function renumeroter(): void
    {
        $this->enregistrerOrdre(Partenaire::dansLOrdre()->pluck('id')->all());
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function enregistrerOrdre(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Partenaire::whereKey($id)->update(['ordre' => $position + 1]);
            }
        });
    }

    private function viderFormulaire(): void
    {
        $this->reset('edition', 'nom', 'url', 'visible', 'logo');
        $this->resetErrorBag();
    }

    private function urlApercuLogo(): ?string
    {
        if ($this->logo !== null && ! $this->getErrorBag()->has('logo')) {
            try {
                return $this->logo->temporaryUrl();
            } catch (\Throwable) {
                // Aperçu impossible : on affiche le logo actuel.
            }
        }

        return $this->edition ? Partenaire::find($this->edition)?->urlLogo() : null;
    }

    public function render(): View
    {
        return view('livewire.site.gestion-partenaires', [
            'partenaires' => Partenaire::dansLOrdre()->get(),
            'urlApercu' => $this->urlApercuLogo(),
        ]);
    }
}
