<?php

namespace App\Livewire\Site;

use App\Models\Annonce;
use App\Services\FichiersAnnonce;
use App\Support\NettoyeurHtml;
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
 * Gestion des annonces de la page d'accueil (comité et administrateur).
 */
#[Title('Annonces')]
class GestionAnnonces extends Component
{
    use WithFileUploads;

    public const LIENS_MAX = 6;

    /** null = pas de formulaire ouvert ; 0 = nouvelle annonce ; sinon identifiant de l'annonce modifiée. */
    #[Locked]
    public ?int $edition = null;

    public string $titre = '';

    public string $contenu = '';

    public string $position_image = 'droite';

    public string $debut_le = '';

    public string $fin_le = '';

    public bool $visible = true;

    public ?TemporaryUploadedFile $image = null;

    public bool $retirerImage = false;

    /** @var array<int, array{libelle: string, type: string, url: string, fichier: ?string, nom_original: ?string}> */
    public array $liens = [];

    /** @var array<int, TemporaryUploadedFile|null> Nouveaux fichiers joints, par numéro de lien. */
    public array $fichiersLiens = [];

    public ?string $confirmation = null;

    public function mount(): void
    {
        $this->authorize('gerer-contenu');

        if (request()->filled('modifier')) {
            $this->modifier((int) request()->query('modifier'));
        } elseif (request()->boolean('nouvelle')) {
            $this->ajouter();
        }
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
        $annonce = Annonce::find($id);

        if ($annonce === null) {
            return;
        }

        $this->viderFormulaire();
        $this->edition = $annonce->id;
        $this->titre = $annonce->titre;
        $this->contenu = (string) $annonce->contenu;
        $this->position_image = $annonce->position_image;
        $this->debut_le = (string) $annonce->debut_le?->toDateString();
        $this->fin_le = (string) $annonce->fin_le?->toDateString();
        $this->visible = $annonce->visible;
        $this->liens = array_map(fn (array $lien) => [
            'libelle' => (string) ($lien['libelle'] ?? ''),
            'type' => (string) ($lien['type'] ?? 'url'),
            'url' => (string) ($lien['url'] ?? ''),
            'fichier' => $lien['fichier'] ?? null,
            'nom_original' => $lien['nom_original'] ?? null,
        ], $annonce->liens ?? []);
    }

    public function annuler(): void
    {
        $this->viderFormulaire();
    }

    public function ajouterLien(): void
    {
        if (count($this->liens) < self::LIENS_MAX) {
            $this->liens[] = ['libelle' => '', 'type' => 'url', 'url' => 'https://', 'fichier' => null, 'nom_original' => null];
        }
    }

    public function retirerLien(int $index): void
    {
        unset($this->liens[$index], $this->fichiersLiens[$index]);
        $this->liens = array_values($this->liens);
        $this->fichiersLiens = array_values($this->fichiersLiens);
        $this->resetErrorBag();
    }

    public function retirerLImage(): void
    {
        $this->reset('image');
        $this->retirerImage = true;
    }

    public function updatedImage(): void
    {
        $this->retirerImage = false;
        $this->validateOnly('image');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // L'adresse n'est vérifiée que pour les boutons de type « lien vers un site ».
        $reglesAdresses = [];
        foreach ($this->liens as $index => $lien) {
            $reglesAdresses["liens.$index.url"] = ($lien['type'] ?? 'url') === 'url'
                ? ['required', 'url:http,https', 'max:500']
                : ['nullable'];
        }

        return [
            ...$reglesAdresses,
            'titre' => ['required', 'string', 'max:150'],
            'contenu' => ['nullable', 'string', 'max:50000'],
            'position_image' => ['required', Rule::in(array_keys(Annonce::POSITIONS_IMAGE))],
            'debut_le' => ['nullable', 'date'],
            'fin_le' => ['nullable', 'date', 'after_or_equal:debut_le'],
            'visible' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'liens' => ['array', 'max:'.self::LIENS_MAX],
            'liens.*.libelle' => ['required', 'string', 'max:80'],
            'liens.*.type' => ['required', Rule::in(['url', 'fichier'])],
            'fichiersLiens.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'fin_le.after_or_equal' => 'La date de fin ne peut pas être avant la date de début.',
            'image.image' => 'Le fichier choisi n\'est pas une image.',
            'image.mimes' => 'L\'image doit être au format JPEG, PNG ou WebP.',
            'image.max' => 'L\'image ne doit pas dépasser 10 Mo.',
            'liens.*.libelle.required' => 'Indiquez le texte du bouton.',
            'liens.*.url.required' => 'Indiquez l\'adresse du lien.',
            'liens.*.url.url' => 'L\'adresse doit commencer par https:// (ou http://).',
            'fichiersLiens.*.mimes' => 'Le fichier doit être un PDF ou une image (JPEG, PNG).',
            'fichiersLiens.*.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
        ];
    }

    public function enregistrer(FichiersAnnonce $fichiers): void
    {
        $this->authorize('gerer-contenu');

        if ($this->edition === null) {
            return;
        }

        $this->validate();

        $estNouvelle = $this->edition === 0;
        $annonce = $estNouvelle ? new Annonce : Annonce::findOrFail($this->edition);

        // Seuls les fichiers déjà attachés à CETTE annonce peuvent être conservés.
        $fichiersExistants = collect($annonce->liens ?? [])->where('type', 'fichier')->pluck('fichier')->filter()->all();

        foreach ($this->liens as $index => $lien) {
            if ($lien['type'] === 'fichier' && empty($this->fichiersLiens[$index])
                && ! in_array($lien['fichier'], $fichiersExistants, true)) {
                throw ValidationException::withMessages(["fichiersLiens.$index" => 'Choisissez le fichier à joindre (PDF ou image).']);
            }
        }

        DB::transaction(function () use ($annonce, $estNouvelle, $fichiers, $fichiersExistants): void {
            $annonce->fill([
                'titre' => trim($this->titre),
                'contenu' => NettoyeurHtml::estVide($this->contenu) ? null : NettoyeurHtml::nettoyer($this->contenu),
                'position_image' => $this->position_image,
                'debut_le' => $this->debut_le ?: null,
                'fin_le' => $this->fin_le ?: null,
                'visible' => $this->visible,
            ]);

            if ($estNouvelle) {
                $annonce->ordre = (int) Annonce::max('ordre') + 1;
                $annonce->save(); // pour obtenir son numéro, utilisé dans le nom des fichiers
            }

            if ($this->image !== null) {
                $annonce->image = $fichiers->enregistrerImage($annonce, $this->image);
            } elseif ($this->retirerImage && $annonce->image !== null) {
                $fichiers->supprimerImage($annonce);
                $annonce->image = null;
            }

            $liens = [];
            foreach ($this->liens as $index => $lien) {
                if ($lien['type'] === 'url') {
                    $liens[] = ['libelle' => trim($lien['libelle']), 'type' => 'url', 'url' => trim($lien['url'])];

                    continue;
                }

                [$fichier, $nomOriginal] = ! empty($this->fichiersLiens[$index])
                    ? $fichiers->enregistrerFichier($annonce, $this->fichiersLiens[$index], $lien['libelle'])
                    : [$lien['fichier'], $lien['nom_original']];

                $liens[] = ['libelle' => trim($lien['libelle']), 'type' => 'fichier', 'fichier' => $fichier, 'nom_original' => $nomOriginal];
            }

            // Fichiers qui ne sont plus utilisés : archivés puis supprimés.
            $fichiersGardes = collect($liens)->pluck('fichier')->filter()->all();
            foreach (array_diff($fichiersExistants, $fichiersGardes) as $fichierRetire) {
                $fichiers->supprimerFichier($annonce, $fichierRetire);
            }

            $annonce->liens = $liens === [] ? null : $liens;
            $annonce->save();
            $annonce->journaliserFichiersEnAttente();
        });

        session()->flash('message', $estNouvelle
            ? 'L\'annonce « '.$annonce->titre.' » a été créée.'
            : 'L\'annonce « '.$annonce->titre.' » a été enregistrée.');

        $this->redirectRoute('annonces.index', navigate: false);
    }

    public function basculerVisibilite(int $id): void
    {
        $this->authorize('gerer-contenu');
        $annonce = Annonce::findOrFail($id);
        $annonce->update(['visible' => ! $annonce->visible]);
    }

    public function supprimer(int $id, FichiersAnnonce $fichiers): void
    {
        $this->authorize('gerer-contenu');
        $annonce = Annonce::findOrFail($id);

        $fichiers->supprimerTout($annonce);
        $annonce->delete();

        if ($this->edition === $id) {
            $this->viderFormulaire();
        }

        $this->confirmation = 'L\'annonce « '.$annonce->titre.' » a été supprimée (elle peut être restaurée depuis le journal).';
    }

    /**
     * Monte (-1) ou descend (+1) une annonce.
     */
    public function deplacer(int $id, int $sens): void
    {
        $this->authorize('gerer-contenu');

        $ids = Annonce::dansLOrdre()->pluck('id')->all();
        $position = array_search($id, $ids, true);
        $cible = $position === false ? false : $position + ($sens < 0 ? -1 : 1);

        if ($position === false || $cible < 0 || $cible >= count($ids)) {
            return;
        }

        [$ids[$position], $ids[$cible]] = [$ids[$cible], $ids[$position]];

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $rang => $identifiant) {
                Annonce::whereKey($identifiant)->update(['ordre' => $rang + 1]);
            }
        });
    }

    private function viderFormulaire(): void
    {
        $this->reset('edition', 'titre', 'contenu', 'position_image', 'debut_le', 'fin_le', 'visible', 'image', 'retirerImage', 'liens', 'fichiersLiens', 'confirmation');
        $this->resetErrorBag();
    }

    private function urlApercuImage(): ?string
    {
        if ($this->image !== null && ! $this->getErrorBag()->has('image')) {
            try {
                return $this->image->temporaryUrl();
            } catch (\Throwable) {
                // Aperçu impossible : on affiche l'image actuelle.
            }
        }

        if ($this->retirerImage || ! $this->edition) {
            return null;
        }

        return Annonce::find($this->edition)?->urlImage();
    }

    public function render(): View
    {
        return view('livewire.site.gestion-annonces', [
            'annonces' => Annonce::dansLOrdre()->get(),
            'urlApercu' => $this->urlApercuImage(),
            'positions' => Annonce::POSITIONS_IMAGE,
        ]);
    }
}
