<?php

namespace App\Livewire\Cours;

use App\Models\Salle;
use App\Services\PhotosSalle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Gestion des salles (comité et administrateur) : nom, adresse, photo, carte Google, informations pratiques.
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

    public ?TemporaryUploadedFile $photo = null;

    public bool $retirerPhoto = false;

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
    }

    public function annuler(): void
    {
        $this->viderFormulaire();
    }

    public function updatedPhoto(): void
    {
        $this->retirerPhoto = false;
        $this->validateOnly('photo');
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
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['npa.regex' => 'Le NPA doit contenir 4 chiffres (ex. 1040).'];
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

        DB::transaction(function () use ($salle, $photos): void {
            $salle->fill([
                'nom' => trim($this->nom),
                'rue' => $this->texteOuNull($this->rue),
                'npa' => $this->texteOuNull($this->npa),
                'localite' => $this->texteOuNull($this->localite),
                'lien_carte' => $this->texteOuNull($this->lien_carte),
                'informations' => $this->texteOuNull($this->informations),
            ]);
            $salle->save();

            if ($this->photo !== null) {
                $salle->photo = $photos->enregistrer($salle, $this->photo->getRealPath());
                $salle->save();
            } elseif ($this->retirerPhoto && $salle->photo !== null) {
                $photos->supprimer($salle);
                $salle->photo = null;
                $salle->save();
            }
        });

        $nom = $salle->nom;
        $this->viderFormulaire();
        $this->confirmation = 'La salle « '.$nom.' » a été enregistrée.';
    }

    public function supprimer(int $id, PhotosSalle $photos): void
    {
        $this->authorize('gerer-cours');
        $salle = Salle::withCount(['cours', 'sessions'])->findOrFail($id);

        if ($salle->cours_count + $salle->sessions_count > 0) {
            $this->confirmation = 'La salle « '.$salle->nom.' » est utilisée par des cours : choisissez d’abord une autre salle pour ces cours.';

            return;
        }

        $photos->supprimer($salle);
        $salle->delete();
        $this->confirmation = 'La salle « '.$salle->nom.' » a été supprimée (elle peut être restaurée depuis le journal).';
    }

    private function texteOuNull(string $valeur): ?string
    {
        return trim($valeur) === '' ? null : trim($valeur);
    }

    private function viderFormulaire(): void
    {
        $this->reset('edition', 'nom', 'rue', 'npa', 'localite', 'lien_carte', 'informations', 'photo', 'retirerPhoto', 'confirmation');
        $this->resetValidation();
    }

    public function render(): View
    {
        $salleEditee = $this->edition ? Salle::find($this->edition) : null;

        // Aperçu de la carte à partir de l'adresse en cours de saisie.
        $apercu = new Salle(['nom' => $this->nom, 'rue' => $this->rue, 'npa' => $this->npa, 'localite' => $this->localite, 'lien_carte' => $this->lien_carte ?: null]);

        return view('livewire.cours.gestion-salles', [
            'salles' => Salle::withCount(['cours', 'sessions'])->orderBy('nom')->get(),
            'salleEditee' => $salleEditee,
            'apercu' => $apercu,
        ]);
    }
}
