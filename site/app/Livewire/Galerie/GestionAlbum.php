<?php

namespace App\Livewire\Galerie;

use App\Models\Album;
use App\Models\PhotoGalerie;
use App\Services\PhotosGalerie;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Création et gestion d'un album de la galerie (moniteurs, comité, administrateur) :
 * informations de l'album, ajout des photos (une par une, envoyées par le navigateur),
 * photo de couverture, suppression de photos ou de l'album (corbeille).
 */
class GestionAlbum extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $albumId = null;

    public string $titre = '';

    public string $date = '';

    public string $description = '';

    public bool $visible = true;

    /** Photo en cours d'envoi (le navigateur les envoie une par une). */
    public ?TemporaryUploadedFile $photoEnvoyee = null;

    /** @var array<int, string> */
    public array $erreursEnvoi = [];

    public ?string $confirmation = null;

    public function mount(?Album $album = null): void
    {
        $this->authorize('gerer-galerie');

        if ($album === null || ! $album->exists) {
            $this->date = now()->toDateString();

            return;
        }

        $this->albumId = $album->id;
        $this->titre = $album->titre;
        $this->date = (string) $album->date?->toDateString();
        $this->description = (string) $album->description;
        $this->visible = $album->estPublie();
    }

    public function enregistrerAlbum(): void
    {
        $this->authorize('gerer-galerie');

        $donnees = $this->validate([
            'titre' => ['required', 'string', 'max:150'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visible' => ['boolean'],
        ], ['titre.required' => 'Le titre de l\'album est obligatoire.']);

        $donnees['titre'] = trim($donnees['titre']);
        $donnees['date'] = $donnees['date'] ?: null;
        $donnees['description'] = trim((string) $donnees['description']) ?: null;

        if ($this->albumId === null) {
            $album = new Album($donnees);
            $album->slug = Album::slugUnique($donnees['titre']);
            $album->save();

            session()->flash('message', 'L\'album « '.$album->titre.' » a été créé. Ajoutez maintenant les photos.');
            $this->redirectRoute('galerie.gestion', $album, navigate: false);

            return;
        }

        $this->album()->update($donnees);
        $this->confirmation = 'Les informations de l\'album ont été enregistrées.';
    }

    /**
     * Appelé à chaque photo envoyée par le navigateur.
     */
    public function updatedPhotoEnvoyee(): void
    {
        $this->authorize('gerer-galerie');

        $photo = $this->photoEnvoyee;
        $this->photoEnvoyee = null;

        if ($photo === null || $this->albumId === null) {
            return;
        }

        $nom = $photo->getClientOriginalName();

        if ($photo->getSize() > 25 * 1024 * 1024) {
            $this->erreursEnvoi[] = '« '.$nom.' » est trop lourde (25 Mo au maximum).';

            return;
        }

        if (! in_array(strtolower($photo->getClientOriginalExtension() ?: (string) $photo->guessExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)
            || ! str_starts_with((string) $photo->getMimeType(), 'image/')) {
            $this->erreursEnvoi[] = '« '.$nom.' » n\'est pas une image JPEG, PNG ou WebP.';

            return;
        }

        $album = $this->album();

        if ($album->photos()->count() >= Album::PHOTOS_MAX) {
            $this->erreursEnvoi[] = '« '.$nom.' » : l\'album contient déjà '.Album::PHOTOS_MAX.' photos (maximum). Créez un deuxième album.';

            return;
        }

        try {
            app(PhotosGalerie::class)->ajouter($album, $photo->getRealPath(), $nom);
        } catch (ValidationException) {
            $this->erreursEnvoi[] = '« '.$nom.' » ne peut pas être lue. Essayez avec une photo JPEG ou PNG.';
        }
    }

    public function effacerErreurs(): void
    {
        $this->reset('erreursEnvoi', 'confirmation');
    }

    public function choisirCouverture(int $photoId): void
    {
        $this->authorize('gerer-galerie');
        $photo = $this->album()->photos()->findOrFail($photoId);
        $this->album()->forceFill(['couverture_id' => $photo->id])->save();
    }

    public function supprimerPhoto(int $photoId): void
    {
        $this->authorize('gerer-galerie');
        $album = $this->album();
        $photo = $album->photos()->findOrFail($photoId);

        if ($album->couverture_id === $photo->id) {
            $album->forceFill(['couverture_id' => null])->save();
        }

        $photo->delete();
    }

    /**
     * Met l'album et toutes ses photos à la corbeille (restaurable depuis le journal).
     */
    public function supprimerAlbum(): void
    {
        $this->authorize('gerer-galerie');
        $album = $this->album();

        DB::transaction(function () use ($album): void {
            PhotoGalerie::where('album_id', $album->id)->update(['deleted_at' => now()]);
            $album->delete();
        });

        session()->flash('message', 'L\'album « '.$album->titre.' » a été supprimé. L\'administrateur peut le restaurer depuis le journal.');
        $this->redirectRoute('galerie.index', navigate: false);
    }

    private function album(): Album
    {
        return Album::findOrFail($this->albumId);
    }

    public function render(): View
    {
        $album = $this->albumId ? $this->album() : null;

        return view('livewire.galerie.gestion-album', [
            'album' => $album,
            'photos' => $album?->photos()->get() ?? collect(),
        ])->title($album ? 'Gérer l’album « '.$album->titre.' »' : 'Nouvel album');
    }
}
