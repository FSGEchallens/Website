<?php

namespace App\Livewire\Concerns;

use App\Enums\Civilite;
use App\Models\User;
use App\Services\PhotoMembre;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Import, aperçu et retrait de la photo d'un membre (fiche du comité et « Mon profil »).
 */
trait GerePhotoMembre
{
    use WithFileUploads;

    /** Nouvelle photo choisie (pas encore enregistrée). */
    public ?TemporaryUploadedFile $photo = null;

    public bool $supprimerPhoto = false;

    public function retirerPhoto(): void
    {
        $this->reset('photo');
        $this->resetErrorBag('photo');
        $this->supprimerPhoto = true;
    }

    public function updatedPhoto(): void
    {
        $this->supprimerPhoto = false;
        $this->validateOnly('photo', $this->reglesPhoto(), $this->messagesPhoto());
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function reglesPhoto(): array
    {
        return ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']];
    }

    /**
     * @return array<string, string>
     */
    protected function messagesPhoto(): array
    {
        return [
            'photo.image' => 'Le fichier choisi n\'est pas une image.',
            'photo.mimes' => 'La photo doit être au format JPEG, PNG ou WebP.',
            'photo.max' => 'La photo ne doit pas dépasser 10 Mo.',
            'photo.uploaded' => 'La photo n\'a pas pu être envoyée (fichier trop lourd ?).',
        ];
    }

    /**
     * Enregistre, retire ou renomme la photo selon ce qui a été fait dans le formulaire.
     */
    protected function appliquerPhoto(User $membre, PhotoMembre $photos): void
    {
        if ($this->photo !== null) {
            $membre->forceFill(['photo' => $photos->enregistrer($membre, $this->photo)]);

            // Même nom de fichier : on « touche » quand même la fiche, pour le journal et le cache des navigateurs.
            $membre->isDirty() ? $membre->save() : $membre->touch();
            $membre->journaliserFichiersEnAttente();
        } elseif ($this->supprimerPhoto && $membre->photo !== null) {
            $photos->supprimer($membre);
            $membre->forceFill(['photo' => null])->save();
        } elseif ($membre->photo !== null) {
            $membre->forceFill(['photo' => $photos->renommerSiNecessaire($membre)])->save();
        }

        $this->reset('photo', 'supprimerPhoto');
    }

    /**
     * Image de l'aperçu : nouvelle photo choisie, photo actuelle, ou image par défaut selon le titre.
     */
    protected function urlApercuPhoto(?User $membre, ?Civilite $civilite): string
    {
        if ($this->photo !== null && ! $this->getErrorBag()->has('photo')) {
            try {
                return $this->photo->temporaryUrl();
            } catch (\Throwable) {
                // Aperçu impossible : on affiche l'image actuelle.
            }
        }

        if ($membre?->photo !== null && ! $this->supprimerPhoto) {
            return $membre->urlPhoto();
        }

        return User::urlPhotoParDefaut($civilite);
    }

    protected function aUnePhoto(?User $membre): bool
    {
        return $this->photo !== null || ($membre?->photo !== null && ! $this->supprimerPhoto);
    }
}
