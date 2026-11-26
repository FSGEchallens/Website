<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Choix d'un nouveau mot de passe depuis le lien reçu par e-mail
 * (mot de passe oublié ou invitation d'un nouveau membre).
 */
class NouveauMotDePasse extends Component
{
    #[Locked]
    public string $token = '';

    #[Locked]
    public bool $invitation = false;

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token, bool $invitation = false): void
    {
        $this->token = $token;
        $this->invitation = $invitation || request()->routeIs('bienvenue');
        $this->email = (string) request()->query('email', '');
    }

    public function enregistrer(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegleMotDePasse::defaults()],
        ], attributes: ['email' => 'e-mail', 'password' => 'mot de passe']);

        $statut = Password::broker($this->invitation ? 'invitations' : 'users')->reset(
            [
                'email' => mb_strtolower(trim($this->email)),
                'password' => $this->password,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($statut !== Password::PASSWORD_RESET) {
            $this->reset('password', 'password_confirmation');

            throw ValidationException::withMessages([
                'email' => 'Ce lien n\'est plus valable ou ne correspond pas à cette adresse e-mail. Demandez un nouveau lien avec « Mot de passe oublié ».',
            ]);
        }

        session()->flash('message', 'Votre mot de passe a été enregistré. Vous pouvez maintenant vous connecter.');

        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.nouveau-mot-de-passe')
            ->title($this->invitation ? 'Bienvenue' : 'Nouveau mot de passe');
    }
}
