<?php

namespace App\Livewire\Site;

use App\Models\Resultat;
use App\Services\PhotosResultat;
use App\Support\NettoyeurHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Gestion des résultats de concours (moniteurs, comité, administrateur).
 */
#[Title('Résultats')]
class GestionResultats extends Component
{
    use WithFileUploads, WithPagination;

    /** null = pas de formulaire ; 0 = nouveau résultat ; sinon identifiant du résultat modifié. */
    #[Locked]
    public ?int $edition = null;

    public string $date = '';

    public string $date_fin = '';

    public string $titre = '';

    public string $contenu = '';

    public bool $visible = true;

    /** @var array<int, string> Photos déjà enregistrées, dans l'ordre. */
    public array $photos = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $nouvellesPhotos = [];

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public ?string $confirmation = null;

    public function mount(): void
    {
        $this->authorize('publier-resultats');

        if (request()->filled('modifier')) {
            $this->modifier((int) request()->query('modifier'));
        } elseif (request()->boolean('nouveau')) {
            $this->ajouter();
        }
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function ajouter(): void
    {
        $this->authorize('publier-resultats');
        $this->viderFormulaire();
        $this->edition = 0;
        $this->date = now()->toDateString();
    }

    public function modifier(int $id): void
    {
        $this->authorize('publier-resultats');
        $resultat = Resultat::find($id);

        if ($resultat === null) {
            return;
        }

        $this->viderFormulaire();
        $this->edition = $resultat->id;
        $this->date = $resultat->date->toDateString();
        $this->date_fin = (string) $resultat->date_fin?->toDateString();
        $this->titre = $resultat->titre;
        $this->contenu = (string) $resultat->contenu;
        $this->visible = $resultat->estAffichable();
        $this->photos = array_values($resultat->photos ?? []);
    }

    public function annuler(): void
    {
        $this->viderFormulaire();
    }

    public function retirerPhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function retirerNouvellePhoto(int $index): void
    {
        unset($this->nouvellesPhotos[$index]);
        $this->nouvellesPhotos = array_values($this->nouvellesPhotos);
    }

    /**
     * Déplace une photo déjà enregistrée vers la gauche (-1) ou la droite (+1).
     */
    public function deplacerPhoto(int $index, int $sens): void
    {
        $cible = $index + ($sens < 0 ? -1 : 1);

        if (isset($this->photos[$index], $this->photos[$cible])) {
            [$this->photos[$index], $this->photos[$cible]] = [$this->photos[$cible], $this->photos[$index]];
        }
    }

    public function updatedNouvellesPhotos(): void
    {
        $this->validateOnly('nouvellesPhotos.*');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date'],
            'titre' => ['required', 'string', 'max:200'],
            'contenu' => ['nullable', 'string', 'max:50000'],
            'visible' => ['boolean'],
            'nouvellesPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'date.required' => 'La date est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin ne peut pas être avant la date du concours.',
            'titre.required' => 'Le titre est obligatoire.',
            'nouvellesPhotos.*.image' => 'Un des fichiers choisis n\'est pas une image.',
            'nouvellesPhotos.*.mimes' => 'Les photos doivent être au format JPEG, PNG ou WebP.',
            'nouvellesPhotos.*.max' => 'Une photo ne doit pas dépasser 15 Mo.',
        ];
    }

    public function enregistrer(PhotosResultat $service): void
    {
        $this->authorize('publier-resultats');

        if ($this->edition === null) {
            return;
        }

        $this->validate();

        $estNouveau = $this->edition === 0;
        $resultat = $estNouveau ? new Resultat : Resultat::findOrFail($this->edition);

        // Seules les photos déjà attachées à CE résultat peuvent être conservées.
        $photosExistantes = $resultat->photos ?? [];
        $photosGardees = array_values(array_filter($this->photos, fn ($nom) => in_array($nom, $photosExistantes, true)));

        if (count($photosGardees) + count($this->nouvellesPhotos) > Resultat::PHOTOS_MAX) {
            throw ValidationException::withMessages(['nouvellesPhotos' => 'Au maximum '.Resultat::PHOTOS_MAX.' photos par résultat.']);
        }

        DB::transaction(function () use ($resultat, $estNouveau, $service, $photosExistantes, $photosGardees): void {
            $resultat->fill([
                'date' => $this->date,
                'date_fin' => $this->date_fin ?: null,
                'titre' => trim($this->titre),
                'contenu' => NettoyeurHtml::estVide($this->contenu) ? null : NettoyeurHtml::nettoyer($this->contenu),
                'visible' => $this->visible,
            ]);

            if ($estNouveau) {
                $resultat->save(); // pour obtenir son numéro, utilisé dans le nom des photos
            }

            foreach (array_diff($photosExistantes, $photosGardees) as $photoRetiree) {
                $service->supprimer($resultat, $photoRetiree);
            }

            foreach ($this->nouvellesPhotos as $fichier) {
                $photosGardees[] = $service->enregistrer($resultat, $fichier->getRealPath());
            }

            $resultat->photos = $photosGardees === [] ? null : $photosGardees;
            $resultat->save();
            $resultat->journaliserFichiersEnAttente();
        });

        session()->flash('message', $estNouveau
            ? 'Le résultat « '.$resultat->titre.' » a été publié.'
            : 'Le résultat « '.$resultat->titre.' » a été enregistré.');

        $this->redirectRoute('resultats.gestion', navigate: false);
    }

    public function basculerVisibilite(int $id): void
    {
        $this->authorize('publier-resultats');
        $resultat = Resultat::findOrFail($id);
        $resultat->update(['visible' => ! $resultat->estAffichable()]);
    }

    public function supprimer(int $id, PhotosResultat $service): void
    {
        $this->authorize('publier-resultats');
        $resultat = Resultat::findOrFail($id);

        $service->supprimerTout($resultat);
        $resultat->delete();

        if ($this->edition === $id) {
            $this->viderFormulaire();
        }

        $this->confirmation = 'Le résultat « '.$resultat->titre.' » a été supprimé (il peut être restauré depuis le journal).';
    }

    private function viderFormulaire(): void
    {
        $this->reset('edition', 'date', 'date_fin', 'titre', 'contenu', 'visible', 'photos', 'nouvellesPhotos', 'confirmation');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $terme = trim($this->recherche);

        return view('livewire.site.gestion-resultats', [
            'resultats' => Resultat::query()
                ->when($terme !== '', fn ($q) => $q->where('titre', 'like', '%'.$terme.'%'))
                ->duPlusRecent()
                ->paginate(20),
            'resultatEdite' => $this->edition ? Resultat::find($this->edition) : null,
        ]);
    }
}
