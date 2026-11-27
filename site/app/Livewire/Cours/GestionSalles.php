<?php

namespace App\Livewire\Cours;

use App\Models\Batiment;
use App\Models\Cours;
use App\Models\Salle;
use App\Models\SessionCours;
use App\Services\PhotosBatiment;
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
 * Gestion des bâtiments (adresse, 1 à 3 photos, carte Google, informations pratiques) et des salles
 * qu'ils contiennent (comité et administrateur).
 */
#[Title('Bâtiments et salles')]
class GestionSalles extends Component
{
    use WithFileUploads;

    /** null = pas de fenêtre ouverte ; 0 = nouveau bâtiment ; sinon identifiant du bâtiment modifié. */
    #[Locked]
    public ?int $edition = null;

    public string $nom = '';

    public string $rue = '';

    public string $npa = '';

    public string $localite = '';

    /** Coordonnées GPS copiées depuis Google Maps, ex. « 46.636370, 6.633660 ». */
    public string $coordonnees = '';

    public string $lien_carte = '';

    public string $informations = '';

    /** @var array<int, string> photos déjà enregistrées et gardées, dans l'ordre */
    public array $photos = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $nouvellesPhotos = [];

    /** Nom des salles à créer avec un nouveau bâtiment (une par ligne). */
    public string $sallesInitiales = '';

    /** Saisie « + Salle » par bâtiment (identifiant du bâtiment => nom). */
    public array $nouvelleSalle = [];

    /** Salle en cours de renommage, et son nouveau nom. */
    #[Locked]
    public ?int $salleRenommee = null;

    public string $nomSalle = '';

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
        $this->sallesInitiales = 'Salle principale';
    }

    public function modifier(int $id): void
    {
        $this->authorize('gerer-cours');
        $batiment = Batiment::findOrFail($id);

        $this->viderFormulaire();
        $this->edition = $batiment->id;
        $this->nom = $batiment->nom;
        $this->rue = (string) $batiment->rue;
        $this->npa = (string) $batiment->npa;
        $this->localite = (string) $batiment->localite;
        $this->coordonnees = (string) $batiment->coordonnees();
        $this->lien_carte = (string) $batiment->lien_carte;
        $this->informations = (string) $batiment->informations;
        $this->photos = $batiment->photos ?? [];
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
            'nom' => ['required', 'string', 'max:150', Rule::unique('batiments', 'nom')->ignore($this->edition ?: null)],
            'rue' => ['nullable', 'string', 'max:150'],
            'npa' => ['nullable', 'string', 'regex:/^\d{4,5}$/'],
            'localite' => ['nullable', 'string', 'max:100'],
            'coordonnees' => ['nullable', 'string', 'max:60', function (string $attribut, mixed $valeur, \Closure $echec): void {
                if (trim((string) $valeur) !== '' && Batiment::lireCoordonnees((string) $valeur) === null) {
                    $echec('Les coordonnées doivent avoir la forme « 46.636370, 6.633660 » (latitude, longitude).');
                }
            }],
            'lien_carte' => ['nullable', 'url:https', 'max:500'],
            'informations' => ['nullable', 'string', 'max:2000'],
            'sallesInitiales' => ['nullable', 'string', 'max:2000'],
            'nouvellesPhotos' => ['array', 'max:'.Batiment::PHOTOS_MAX],
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
            'nouvellesPhotos.max' => 'Au maximum '.Batiment::PHOTOS_MAX.' photos par bâtiment.',
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
        return ['rue' => 'rue', 'npa' => 'NPA', 'localite' => 'localité', 'coordonnees' => 'coordonnées', 'lien_carte' => 'lien Google Maps', 'sallesInitiales' => 'salles'];
    }

    public function enregistrer(PhotosBatiment $photos): void
    {
        $this->authorize('gerer-cours');
        $this->validate();

        $batiment = $this->edition ? Batiment::findOrFail($this->edition) : new Batiment;
        $estNouveau = ! $batiment->exists;

        // Seules les photos déjà attachées à CE bâtiment peuvent être conservées.
        $photosExistantes = $batiment->photos ?? [];
        $photosGardees = array_values(array_filter($this->photos, fn ($nom) => in_array($nom, $photosExistantes, true)));

        if (count($photosGardees) + count($this->nouvellesPhotos) > Batiment::PHOTOS_MAX) {
            throw ValidationException::withMessages(['nouvellesPhotos' => 'Au maximum '.Batiment::PHOTOS_MAX.' photos par bâtiment.']);
        }

        $nomsSalles = collect(preg_split('/\r?\n/', $this->sallesInitiales) ?: [])->map(fn ($n) => trim($n))->filter()->unique()->values();

        if ($estNouveau && $nomsSalles->isEmpty()) {
            throw ValidationException::withMessages(['sallesInitiales' => 'Indiquez au moins une salle.']);
        }

        DB::transaction(function () use ($batiment, $photos, $photosExistantes, $photosGardees, $estNouveau, $nomsSalles): void {
            $batiment->fill([
                'nom' => trim($this->nom),
                'rue' => $this->texteOuNull($this->rue),
                'npa' => $this->texteOuNull($this->npa),
                'localite' => $this->texteOuNull($this->localite),
                'latitude' => Batiment::lireCoordonnees($this->coordonnees)[0] ?? null,
                'longitude' => Batiment::lireCoordonnees($this->coordonnees)[1] ?? null,
                'lien_carte' => $this->texteOuNull($this->lien_carte),
                'informations' => $this->texteOuNull($this->informations),
            ]);
            $batiment->save();

            foreach (array_diff($photosExistantes, $photosGardees) as $photoRetiree) {
                $photos->supprimer($batiment, $photoRetiree);
            }

            foreach ($this->nouvellesPhotos as $fichier) {
                $photosGardees[] = $photos->enregistrer($batiment, $fichier->getRealPath());
            }

            $batiment->photos = $photosGardees === [] ? null : $photosGardees;
            $batiment->save();
            $batiment->journaliserFichiersEnAttente();

            if ($estNouveau) {
                foreach ($nomsSalles as $rang => $nomSalle) {
                    $salle = new Salle(['batiment_id' => $batiment->id, 'nom' => mb_substr($nomSalle, 0, 150)]);
                    $salle->forceFill(['ordre' => $rang + 1])->save();
                }
            }
        });

        $nom = $batiment->nom;
        $this->viderFormulaire();
        $this->confirmation = 'Le bâtiment « '.$nom.' » a été enregistré.';
    }

    public function supprimer(int $id, PhotosBatiment $photos): void
    {
        $this->authorize('gerer-cours');
        $batiment = Batiment::withCount('salles')->findOrFail($id);

        if ($batiment->salles_count > 0) {
            $this->confirmation = 'Supprimez d’abord les salles du bâtiment « '.$batiment->nom.' ».';

            return;
        }

        $photos->supprimerTout($batiment);
        $batiment->delete();
        $this->confirmation = 'Le bâtiment « '.$batiment->nom.' » a été supprimé (il peut être restauré depuis le journal).';
    }

    public function ajouterSalle(int $batimentId): void
    {
        $this->authorize('gerer-cours');
        $batiment = Batiment::findOrFail($batimentId);
        $nom = trim((string) ($this->nouvelleSalle[$batimentId] ?? ''));

        $this->validerNomSalle($nom, $batiment->id, null, 'nouvelleSalle.'.$batimentId);

        $salle = new Salle(['batiment_id' => $batiment->id, 'nom' => $nom]);
        $salle->forceFill(['ordre' => (int) Salle::where('batiment_id', $batiment->id)->max('ordre') + 1])->save();

        unset($this->nouvelleSalle[$batimentId]);
        $this->confirmation = 'La salle « '.$salle->libelleComplet().' » a été ajoutée.';
    }

    public function renommerSalle(int $id): void
    {
        $this->authorize('gerer-cours');
        $salle = Salle::findOrFail($id);
        $this->salleRenommee = $salle->id;
        $this->nomSalle = $salle->nom;
        $this->resetValidation();
    }

    public function enregistrerSalle(): void
    {
        $this->authorize('gerer-cours');
        $salle = Salle::findOrFail($this->salleRenommee);
        $nom = trim($this->nomSalle);

        $this->validerNomSalle($nom, $salle->batiment_id, $salle->id, 'nomSalle');

        $salle->nom = $nom;
        $salle->save();
        $this->reset('salleRenommee', 'nomSalle');
    }

    public function annulerSalle(): void
    {
        $this->reset('salleRenommee', 'nomSalle');
        $this->resetValidation();
    }

    public function supprimerSalle(int $id): void
    {
        $this->authorize('gerer-cours');
        $salle = Salle::with('batiment')->findOrFail($id);

        if (array_sum($salle->utilisations()) > 0) {
            $this->confirmation = 'La salle « '.$salle->libelleComplet().' » est utilisée par des cours : choisissez d’abord une autre salle pour ces cours.';

            return;
        }

        $salle->delete();
        $this->confirmation = 'La salle « '.$salle->libelleComplet().' » a été supprimée (elle peut être restaurée depuis le journal).';
    }

    private function validerNomSalle(string $nom, int $batimentId, ?int $ignorer, string $champ): void
    {
        $this->resetValidation($champ);

        if ($nom === '' || mb_strlen($nom) > 150) {
            throw ValidationException::withMessages([$champ => 'Indiquez le nom de la salle (150 caractères au maximum).']);
        }

        if (Salle::where('batiment_id', $batimentId)->where('nom', $nom)->when($ignorer, fn ($q) => $q->whereKeyNot($ignorer))->exists()) {
            throw ValidationException::withMessages([$champ => 'Ce bâtiment a déjà une salle de ce nom.']);
        }
    }

    private function texteOuNull(string $valeur): ?string
    {
        return trim($valeur) === '' ? null : trim($valeur);
    }

    private function viderFormulaire(): void
    {
        $this->reset('edition', 'nom', 'rue', 'npa', 'localite', 'coordonnees', 'lien_carte', 'informations', 'photos', 'nouvellesPhotos', 'sallesInitiales', 'confirmation');
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
        $batimentEdite = $this->edition ? Batiment::find($this->edition) : null;

        // Aperçu de la carte à partir de l'adresse en cours de saisie.
        $coordonnees = Batiment::lireCoordonnees($this->coordonnees);
        $apercu = new Batiment([
            'nom' => $this->nom, 'rue' => $this->rue, 'npa' => $this->npa, 'localite' => $this->localite,
            'latitude' => $coordonnees[0] ?? null, 'longitude' => $coordonnees[1] ?? null, 'lien_carte' => $this->lien_carte ?: null,
        ]);

        return view('livewire.cours.gestion-salles', [
            'batiments' => Batiment::with('salles')->orderBy('nom')->get(),
            'utilisations' => $this->utilisations(),
            'batimentEdite' => $batimentEdite,
            'apercu' => $apercu,
        ]);
    }
}
