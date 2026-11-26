<?php

namespace App\Livewire\Membres;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Livewire\Concerns\GerePhotoMembre;
use App\Models\User;
use App\Rules\NumeroAvs;
use App\Services\PhotoMembre;
use App\Support\NumeroMobile;
use App\Validation\ReglesMembre;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Création et modification d'une fiche membre (comité / webmaster).
 * Une fiche existante s'ouvre verrouillée : il faut cliquer sur « Modifier » pour la changer.
 */
class FicheMembre extends Component
{
    use GerePhotoMembre;

    public ?User $membre = null;

    public bool $enEdition = false;

    public string $civilite = '';

    public string $prenom = '';

    public string $nom = '';

    public string $rue = '';

    public string $numero = '';

    public string $npa = '';

    public string $localite = '';

    public string $mobile = '';

    /** Pays de l'indicatif du mobile (code ISO, Suisse par défaut). */
    public string $mobile_pays = 'CH';

    public string $email_prive = '';

    /** Partie avant le @ de l'e-mail société (le domaine est ajouté automatiquement). */
    public string $identifiant_email = '';

    /** Ancienne adresse société qui n'est pas @gymechallens.ch : conservée tant qu'aucun identifiant n'est saisi. */
    #[Locked]
    public ?string $emailHorsDomaine = null;

    public string $avs = '';

    public string $date_naissance = '';

    public string $date_entree = '';

    public string $date_sortie = '';

    public bool $afficher_mobile = false;

    public string $role = '';

    public function mount(?User $membre = null): void
    {
        if ($membre === null || ! $membre->exists) {
            $this->authorize('gerer-membres');
            $this->date_entree = now()->toDateString();
            $this->role = Role::Membre->value;
            $this->enEdition = true;

            return;
        }

        $this->authorize('modifier-membre', $membre);
        $this->membre = $membre;
        $this->remplir($membre);
    }

    public function modifier(): void
    {
        $this->autoriserModification();
        $this->enEdition = true;
    }

    public function annuler(): void
    {
        if ($this->membre === null) {
            $this->redirectRoute('membres.index', navigate: false);

            return;
        }

        $this->remplir($this->membre->fresh());
        $this->reset('photo', 'supprimerPhoto');
        $this->resetErrorBag();
        $this->enEdition = false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'civilite' => ['required', Rule::enum(Civilite::class)],
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            ...ReglesMembre::coordonnees(),
            'avs' => ['nullable', 'string', new NumeroAvs],
            'date_naissance' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'identifiant_email' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?$/'],
            'date_entree' => ['nullable', 'date'],
            'date_sortie' => ['nullable', 'date', 'after_or_equal:date_entree'],
            ...ReglesMembre::affichage(),
            'role' => ['required', Rule::enum(Role::class)],
            ...$this->reglesPhoto(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            ...ReglesMembre::attributs(),
            'civilite' => 'titre',
            'identifiant_email' => 'e-mail société',
            'avs' => 'n° AVS',
            'date_naissance' => 'date de naissance',
            'date_entree' => 'date d\'entrée',
            'date_sortie' => 'date de sortie',
            'role' => 'autorisation',
            'photo' => 'photo',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            ...ReglesMembre::messages(),
            'date_naissance.before' => 'La date de naissance doit être dans le passé.',
            'date_naissance.after' => 'La date de naissance n\'est pas plausible.',
            'date_sortie.after_or_equal' => 'La date de sortie ne peut pas être avant la date d\'entrée.',
            'identifiant_email.regex' => 'Utilisez seulement des lettres sans accents, des chiffres, des points, des tirets ou des tirets bas (par exemple prenom.nom).',
            ...$this->messagesPhoto(),
        ];
    }

    public static function domaineEmail(): string
    {
        return (string) config('app.domaine_email');
    }

    /**
     * Adresse société complète à partir de l'identifiant saisi (ou l'ancienne adresse conservée).
     */
    private function emailSociete(?string $identifiant): ?string
    {
        if ($identifiant === null) {
            return $this->emailHorsDomaine;
        }

        return mb_strtolower($identifiant).'@'.self::domaineEmail();
    }

    public function enregistrer(PhotoMembre $photos): void
    {
        if (! $this->enEdition) {
            return;
        }

        $this->autoriserModification();

        // Si une adresse complète a été collée, on ne garde que la partie avant le @.
        $this->identifiant_email = trim(explode('@', $this->identifiant_email)[0]);

        $donnees = $this->validate();

        $role = Role::from($donnees['role']);
        $this->verifierRoleAdministrateur($role);
        unset($donnees['role'], $donnees['photo']);

        $donnees = ReglesMembre::nettoyer($donnees);
        $donnees['avs'] = NumeroAvs::formater($donnees['avs']);
        $donnees['email'] = $this->emailSociete($donnees['identifiant_email']);
        unset($donnees['identifiant_email']);

        if ($donnees['email'] !== null && User::where('email', $donnees['email'])->whereKeyNot($this->membre?->getKey())->exists()) {
            throw ValidationException::withMessages([
                'identifiant_email' => 'L\'adresse '.$donnees['email'].' est déjà utilisée par un autre membre.',
            ]);
        }

        $estNouveau = $this->membre === null;
        $membre = $this->membre ?? new User;

        DB::transaction(function () use ($membre, $donnees, $role, $photos): void {
            $membre->fill($donnees)->forceFill(['role' => $role])->save();
            $this->appliquerPhoto($membre, $photos);
        });

        $message = $estNouveau
            ? 'Le membre '.$membre->nomComplet().' a été créé.'
            : 'La fiche de '.$membre->nomComplet().' a été enregistrée.';

        if ($estNouveau && $membre->email !== null) {
            $membre->envoyerInvitation();
            $message .= ' Un e-mail lui a été envoyé pour choisir son mot de passe.';
        }

        session()->flash('message', $message);

        $this->redirectRoute('membres.index', navigate: false);
    }

    /**
     * Renvoie au membre le lien (valable 7 jours) pour choisir son mot de passe.
     */
    public function envoyerLienMotDePasse(): void
    {
        $this->autoriserModification();

        if ($this->membre?->email === null) {
            $this->addError('identifiant_email', 'Ce membre n\'a pas d\'e-mail société enregistré.');

            return;
        }

        $this->membre->envoyerInvitation();

        session()->flash('message', 'Un lien pour choisir son mot de passe a été envoyé à '.$this->membre->email.'.');

        $this->redirectRoute('membres.edit', $this->membre, navigate: false);
    }

    private function remplir(User $membre): void
    {
        $this->fill([
            'civilite' => $membre->civilite?->value ?? '',
            'prenom' => $membre->prenom,
            'nom' => $membre->nom,
            'rue' => (string) $membre->rue,
            'numero' => (string) $membre->numero,
            'npa' => (string) $membre->npa,
            'localite' => (string) $membre->localite,
            'mobile' => NumeroMobile::separer($membre->mobile)['numero'],
            'mobile_pays' => NumeroMobile::separer($membre->mobile)['pays'],
            'email_prive' => (string) $membre->email_prive,
            ...$this->separerEmailSociete($membre->email),
            'avs' => (string) $membre->avs,
            'date_naissance' => (string) $membre->date_naissance?->toDateString(),
            'date_entree' => (string) $membre->date_entree?->toDateString(),
            'date_sortie' => (string) $membre->date_sortie?->toDateString(),
            'afficher_mobile' => (bool) $membre->afficher_mobile,
            'role' => ($membre->role ?? Role::Membre)->value,
        ]);
    }

    /**
     * @return array{identifiant_email: string, emailHorsDomaine: ?string}
     */
    private function separerEmailSociete(?string $email): array
    {
        $suffixe = '@'.self::domaineEmail();

        if ($email !== null && str_ends_with(mb_strtolower($email), $suffixe)) {
            return ['identifiant_email' => substr($email, 0, -strlen($suffixe)), 'emailHorsDomaine' => null];
        }

        return ['identifiant_email' => '', 'emailHorsDomaine' => $email];
    }

    private function autoriserModification(): void
    {
        $this->membre === null
            ? $this->authorize('gerer-membres')
            : $this->authorize('modifier-membre', $this->membre);
    }

    /**
     * Seul un administrateur peut donner ou retirer l'autorisation Administrateur,
     * et il doit toujours en rester au moins un.
     */
    private function verifierRoleAdministrateur(Role $nouveauRole): void
    {
        $estAdministrateur = $this->membre?->estAdministrateur() ?? false;
        $seraAdministrateur = $nouveauRole === Role::Administrateur;

        if ($seraAdministrateur === $estAdministrateur) {
            return;
        }

        if (! auth()->user()->can('attribuer-role-administrateur')) {
            throw ValidationException::withMessages([
                'role' => 'Seul un administrateur peut donner ou retirer l\'autorisation Administrateur.',
            ]);
        }

        if ($estAdministrateur && User::avecRole(Role::Administrateur)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Il doit toujours rester au moins un administrateur.',
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.membres.fiche-membre', [
            'civilites' => Civilite::cases(),
            'tousLesRoles' => Role::cases(),
            'urlApercu' => $this->urlApercuPhoto($this->membre, Civilite::tryFrom($this->civilite)),
            'aUnePhoto' => $this->aUnePhoto($this->membre),
        ])->title($this->membre ? $this->membre->nomComplet() : 'Nouveau membre');
    }
}
