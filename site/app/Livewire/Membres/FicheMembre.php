<?php

namespace App\Livewire\Membres;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Models\User;
use App\Rules\NumeroAvs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * CrÃ©ation et modification d'une fiche membre (comitÃ© / webmaster).
 */
class FicheMembre extends Component
{
    public ?User $membre = null;

    public string $civilite = '';

    public string $prenom = '';

    public string $nom = '';

    public string $surnom = '';

    public string $rue = '';

    public string $numero = '';

    public string $npa = '';

    public string $localite = '';

    public string $telephone = '';

    public string $mobile = '';

    public string $email_prive = '';

    public string $email = '';

    public string $avs = '';

    public string $date_naissance = '';

    public string $date_entree = '';

    public string $date_sortie = '';

    public bool $afficher_photo = false;

    public bool $afficher_adresse = false;

    public bool $afficher_telephone = false;

    public bool $afficher_mobile = false;

    public string $compte_wp = '';

    /** @var array<int, string> */
    public array $roles = [];

    public function mount(?User $membre = null): void
    {
        if ($membre === null || ! $membre->exists) {
            $this->date_entree = now()->toDateString();

            return;
        }

        $this->membre = $membre;

        $this->fill([
            'civilite' => $membre->civilite?->value ?? '',
            'prenom' => $membre->prenom,
            'nom' => $membre->nom,
            'surnom' => (string) $membre->surnom,
            'rue' => (string) $membre->rue,
            'numero' => (string) $membre->numero,
            'npa' => (string) $membre->npa,
            'localite' => (string) $membre->localite,
            'telephone' => (string) $membre->telephone,
            'mobile' => (string) $membre->mobile,
            'email_prive' => (string) $membre->email_prive,
            'email' => (string) $membre->email,
            'avs' => (string) $membre->avs,
            'date_naissance' => (string) $membre->date_naissance?->toDateString(),
            'date_entree' => (string) $membre->date_entree?->toDateString(),
            'date_sortie' => (string) $membre->date_sortie?->toDateString(),
            'afficher_photo' => (bool) $membre->afficher_photo,
            'afficher_adresse' => (bool) $membre->afficher_adresse,
            'afficher_telephone' => (bool) $membre->afficher_telephone,
            'afficher_mobile' => (bool) $membre->afficher_mobile,
            'compte_wp' => (string) $membre->compte_wp,
            'roles' => $membre->roles->map(fn ($userRole) => $userRole->role->value)->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $telephone = ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ().\/-]{6,20}$/'];

        return [
            'civilite' => ['nullable', Rule::enum(Civilite::class)],
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'surnom' => ['nullable', 'string', 'max:100'],
            'rue' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:10'],
            'npa' => ['nullable', 'string', 'regex:/^[0-9]{4,5}$/'],
            'localite' => ['nullable', 'string', 'max:100'],
            'telephone' => $telephone,
            'mobile' => $telephone,
            'email_prive' => ['nullable', 'email', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->membre?->id)],
            'avs' => ['nullable', 'string', new NumeroAvs],
            'date_naissance' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'date_entree' => ['nullable', 'date'],
            'date_sortie' => ['nullable', 'date', 'after_or_equal:date_entree'],
            'afficher_photo' => ['boolean'],
            'afficher_adresse' => ['boolean'],
            'afficher_telephone' => ['boolean'],
            'afficher_mobile' => ['boolean'],
            'compte_wp' => ['nullable', 'string', 'max:60'],
            'roles' => ['array'],
            'roles.*' => ['distinct', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'civilite' => 'titre',
            'npa' => 'code postal',
            'email_prive' => 'e-mail privÃ©',
            'email' => 'e-mail sociÃ©tÃ©',
            'avs' => 'nÂ° AVS',
            'date_naissance' => 'date de naissance',
            'date_entree' => 'date d\'entrÃ©e',
            'date_sortie' => 'date de sortie',
            'compte_wp' => 'compte WordPress',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'date_naissance.before' => 'La date de naissance doit Ãªtre dans le passÃ©.',
            'date_naissance.after' => 'La date de naissance n\'est pas plausible.',
            'date_sortie.after_or_equal' => 'La date de sortie ne peut pas Ãªtre avant la date d\'entrÃ©e.',
            'npa.regex' => 'Le code postal doit contenir 4 chiffres (5 pour l\'Ã©tranger).',
            'telephone.regex' => 'Le numÃ©ro de tÃ©lÃ©phone n\'est pas valide.',
            'mobile.regex' => 'Le numÃ©ro de mobile n\'est pas valide.',
        ];
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate();

        $roles = array_map(fn (string $valeur) => Role::from($valeur), $donnees['roles'] ?? []);
        unset($donnees['roles']);

        $donnees = array_map(fn ($valeur) => is_string($valeur) ? (trim($valeur) === '' ? null : trim($valeur)) : $valeur, $donnees);
        $donnees['avs'] = NumeroAvs::formater($donnees['avs']);
        $donnees['email'] = $donnees['email'] !== null ? mb_strtolower($donnees['email']) : null;

        $estNouveau = $this->membre === null;
        $membre = $this->membre ?? new User;
        DB::transaction(function () use ($membre, $donnees, $roles): void {
            $membre->fill($donnees)->save();
            $membre->synchroniserRoles($roles);
        });

        session()->flash('message', $estNouveau
            ? 'Le membre '.$membre->nomComplet().' a Ã©tÃ© crÃ©Ã©.'
            : 'La fiche de '.$membre->nomComplet().' a Ã©tÃ© enregistrÃ©e.');

        $this->redirectRoute('membres.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.membres.fiche-membre', [
            'civilites' => Civilite::cases(),
            'tousLesRoles' => Role::cases(),
        ])->title($this->membre ? 'Modifier '.$this->membre->nomComplet() : 'Nouveau membre');
    }
}
