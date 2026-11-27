<?php

namespace App\Livewire\Cours;

use App\Models\Cours;
use App\Models\Salle;
use App\Models\SessionCours;
use App\Services\PhotosSalle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Gestion des salles (comité et administrateur) : nom, adresse, 1 à 3 photos, carte Google, informations pratiques.
 */
#[Title('Salles')]
class GestionSalles extends Component
{
    use WithFileUploads;

    /** null = pas de fenêtre ouverte ; 0 = nouvelle salle ; sinon identifiant de la salle modifiée. */
    #[Locked]
    public ?int $edition = null;

    public string $nom = '';

    public string $rue = '';

    public string $npa = '';

    public string $localite = '';

    public string $lien_carte = '';

    public string $informations = '';

    /** @var array<int, string> photos déjà enregistrées et gardées, dans l'ordre */
    public array $photos = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $nouvellesPhotos = [];

    public ?string $confirmation = null;

    public function mount(): void
    {
        $this->authorize('gerer-cours');
    }

    public function ajouter(): void
    {
        $this->authorize('gerer-cours');
        $this->viderFormulaire();
        $this->edition = 0;
    }

    public function modifier(int $id): void
    {
        $this->authorize('gerer-cours');
        $salle = Salle::findOrFail($id);

        $this->viderFormulaire();
        $this->edition = $salle->id;
        $this->nom = $salle->nom;
        $this->rue = (string) $salle->rue;
        $this->npa = (string) $salle->npa;
        $this->localite = (string) $salle->localite;
        $this->lien_carte = (string) $salle->lien_carte;
        $this->informations = (string) $salle->informations;
        $this->photos = $salle->photos ?? [];
    }

    public function annuler(): void
    {
        $this->viderFormulaire();
    }

    public function updatedNouvellesPhotos(): void
    {
        $this->validateOnly('nouvellesPhotos.*');
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
     * Déplace une photo déjà enregistrée vers la gauche (-1) ou la droite (+1) ; la première est la photo principale.
     */
    public function deplacerPhoto(int $index, int $sens): void
    {
        $cible = $index + ($sens < 0 ? -1 : 1);

        if (isset($this->photos[$index], $this->photos[$cible])) {
            [$this->photos[$index], $this->photos[$cible]] = [$this->photos[$cible], $this->photos[$index]];
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150', Rule::unique('salles', 'nom')->ignore($this->edition ?: null)],
            'rue' => ['nullable', 'string', 'max:150'],
            'npa' => ['nullable', 'string', 'regex:/^\d{4,5}$/'],
            'localite' => ['nullable', 'string', 'max:100'],
            'lien_carte' => ['nullable', 'url:https', 'max:500'],
            'informations' => ['nullable', 'string', 'max:2000'],
            'nouvellesPhotos' => ['array', 'max:'.Salle::PHOTOS_MAX],
            'nouvellesPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'npa.regex' => 'Le NPA doit contenir 4 chiffres (ex. 1040).',
            'nouvellesPhotos.max' => 'Au maximum '.Salle::PHOTOS_MAX.' photos par salle.',
            'nouvellesPhotos.*.image' => 'Un des fichiers choisis n’est pas une image.',
            'nouvellesPhotos.*.mimes' => 'Les photos doivent être au format JPEG, PNG ou WebP.',
            'nouvellesPhotos.*.max' => 'Une photo ne doit pas dépasser 15 Mo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['rue' => 'rue', 'npa' => 'NPA', 'localite' => 'localité', 'lien_carte' => 'lien Google Maps'];
    }

    public function enregistrer(PhotosSalle $photos): void
    {
        $this->authorize('gerer-cours');
        $this->validate();

        $salle = $this->edition ? Salle::findOrFail($this->edition) : new Salle;

        // Seules les photos déjà attachées à CETTE salle peuvent être conservées.
        $photosExistantes = $salle->photos ?? [];
        $photosGardees = array_values(array_filter($this->photos, fn ($nom) => in_array($nom, $photosExistantes, true)));

        if (count($photosGardees) + count($this->nouvellesPhotos) > Salle::PHOTOS_MAX) {
            throw ValidationException::withMessages(['nouvellesPhotos' => 'Au maximum '.Salle::PHOTOS_MAX.' photos par salle.']);
        }

        DB::transaction(function () use ($salle, $photos, $photosExistantes, $photosGardees): void {
            $salle->fill([
                'nom' => trim($this->nom),
                'rue' => $this->texteOuNull($this->rue),
                'npa' => $this->texteOuNull($this->npa),
                'localite' => $this->texteOuNull($this->localite),
                'lien_carte' => $this->texteOuNull($this->lien_carte),
                'informations' => $this->texteOuNull($this->informations),
            ]);
            $salle->save();

            foreach (array_diff($photosExistantes, $photosGardees) as $photoRetiree) {
                $photos->supprimer($salle, $photoRetiree);
            }

            foreach ($this->nouvellesPhotos as $fichier) {
                $photosGardees[] = $photos->enregistrer($salle, $fichier->getRealPath());
            }

            $salle->photos = $photosGardees === [] ? null : $photosGardees;
            $salle->save();
            $salle->journaliserFichiersEnAttente();
        });

        $nom = $salle->nom;
        $this->viderFormulaire();
        $this->confirmation = 'La salle « '.$nom.' » a été enregistrée.';
    }

    public function supprimer(int $id, PhotosSalle $photos): void
    {
        $this->authorize('gerer-cours');
        $salle = Salle::findOrFail($id);

        if (array_sum($salle->utilisations()) > 0) {
            $this->confirmation = 'La salle « '.$salle->nom.' » est utilisée par des cours : choisissez d’abord une autre salle pour ces cours.';

            return;
        }

        $photos->supprimerTout($salle);
        $salle->delete();
        $this->confirmation = 'La salle « '.$salle->nom.' » a été supprimée (elle peut être restaurée depuis le journal).';
    }

    private function texteOuNull(string $valeur): ?string
    {
        return trim($valeur) === '' ? null : trim($valeur);
    }

    private function viderFormulaire(): void
    {
        $this->reset('edition', 'nom', 'rue', 'npa', 'localite', 'lien_carte', 'informations', 'photos', 'nouvellesPhotos', 'confirmation');
        $this->resetValidation();
    }

    /**
     * Nombre de cours et de sessions par salle.
     *
     * @return array<int, array{cours: int, sessions: int}>
     */
    private function utilisations(): array
    {
        $comptes = [];

        foreach (['cours' => Cours::class, 'sessions' => SessionCours::class] as $cle => $modele) {
            foreach ($modele::whereNotNull('salles')->pluck('salles') as $salles) {
                foreach (array_unique(array_map('intval', $salles)) as $id) {
                    $comptes[$id] ??= ['cours' => 0, 'sessions' => 0];
                    $comptes[$id][$cle]++;
                }
            }
        }

        return $comptes;
    }

    public function render(): View
    {
        $salleEditee = $this->edition ? Salle::find($this->edition) : null;

        // Aperçu de la carte à partir de l'adresse en cours de saisie.
        $apercu = new Salle(['nom' => $this->nom, 'rue' => $this->rue, 'npa' => $this->npa, 'localite' => $this->localite, 'lien_carte' => $this->lien_carte ?: null]);

        return view('livewire.cours.gestion-salles', [
            'salles' => Salle::orderBy('nom')->get(),
            'utilisations' => $this->utilisations(),
            'salleEditee' => $salleEditee,
            'apercu' => $apercu,
        ]);
    }
}
