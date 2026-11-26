<?php

namespace App\Livewire\Profil;

use App\Livewire\Concerns\GerePhotoMembre;
use App\Models\User;
use App\Services\PhotoMembre;
use App\Validation\ReglesMembre;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Profil du membre connecté : il voit toutes ses données et modifie
 * lui-même sa photo, ses coordonnées, ses choix d'affichage et son mot de passe.
 * Les coordonnées sont verrouillées tant qu'on n'a pas cliqué sur « Modifier ».
 */
#[Title('Mon profil')]
class MonProfil extends Component
{
    use GerePhotoMembre;

    public bool $enEdition = false;

    public string $rue = '';

    public string $numero = '';

    public string $npa = '';

    public string $localite = '';

    public string $telephone = '';

    public string $mobile = '';

    public string $email_prive = '';

    public bool $afficher_photo = false;

    public bool $afficher_adresse = false;

    public bool $afficher_telephone = false;

    public bool $afficher_mobile = false;

    public string $mot_de_passe_actuel = '';

    public string $nouveau_mot_de_passe = '';

    public string $nouveau_mot_de_passe_confirmation = '';

    public function mount(): void
    {
        $this->remplir($this->membre());
    }

    public function modifier(): void
    {
        $this->enEdition = true;
    }

    public function annuler(): void
    {
        $this->remplir($this->membre()->fresh());
        $this->reset('photo', 'supprimerPhoto');
        $this->resetErrorBag();
        $this->enEdition = false;
    }

    public function enregistrerCoordonnees(PhotoMembre $photos): void
    {
        if (! $this->enEdition) {
            return;
        }

        $donnees = $this->validate(
            [...ReglesMembre::coordonnees(), ...ReglesMembre::affichage(), ...$this->reglesPhoto()],
            [...ReglesMembre::messages(), ...$this->messagesPhoto()],
            ReglesMembre::attributs(),
        );
        unset($donnees['photo']);

        $membre = $this->membre();
        $membre->fill(ReglesMembre::nettoyer($donnees))->save();
        $this->appliquerPhoto($membre, $photos);

        session()->flash('message', 'Votre profil a été enregistré.');
        $this->redirectRoute('profil', navigate: false);
    }

    public function changerMotDePasse(): void
    {
        $this->validate([
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'nouveau_mot_de_passe' => ['required', 'confirmed', 'different:mot_de_passe_actuel', RegleMotDePasse::defaults()],
        ], [
            'mot_de_passe_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
            'nouveau_mot_de_passe.different' => 'Le nouveau mot de passe doit être différent de l\'actuel.',
        ], [
            'mot_de_passe_actuel' => 'mot de passe actuel',
            'nouveau_mot_de_passe' => 'nouveau mot de passe',
        ]);

        $this->membre()->forceFill([
            'password' => $this->nouveau_mot_de_passe,
            'remember_token' => Str::random(60),
        ])->save();

        session()->regenerate();

        $this->reset('mot_de_passe_actuel', 'nouveau_mot_de_passe', 'nouveau_mot_de_passe_confirmation');

        session()->flash('message', 'Votre mot de passe a été changé.');
        $this->redirectRoute('profil', navigate: false);
    }

    private function membre(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function remplir(User $membre): void
    {
        $this->fill([
            'rue' => (string) $membre->rue,
            'numero' => (string) $membre->numero,
            'npa' => (string) $membre->npa,
            'localite' => (string) $membre->localite,
            'telephone' => (string) $membre->telephone,
            'mobile' => (string) $membre->mobile,
            'email_prive' => (string) $membre->email_prive,
            'afficher_photo' => (bool) $membre->afficher_photo,
            'afficher_adresse' => (bool) $membre->afficher_adresse,
            'afficher_telephone' => (bool) $membre->afficher_telephone,
            'afficher_mobile' => (bool) $membre->afficher_mobile,
        ]);
    }

    /**
     * N° AVS partiellement masqué : 756.****.****.97.
     */
    private function avsMasque(?string $avs): ?string
    {
        if ($avs === null || strlen($avs) !== 16) {
            return $avs;
        }

        return substr($avs, 0, 4).'****.****'.substr($avs, 13);
    }

    public function render(): View
    {
        $membre = $this->membre();

        return view('livewire.profil.mon-profil', [
            'membre' => $membre,
            'avsMasque' => $this->avsMasque($membre->avs),
            'urlApercu' => $this->urlApercuPhoto($membre, $membre->civilite),
            'aUnePhoto' => $this->aUnePhoto($membre),
        ]);
    }
}
