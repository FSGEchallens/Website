<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Demande d'un lien de réinitialisation. Le message est toujours le même,
 * que le compte existe ou non, pour ne pas révéler qui est membre.
 */
#[Title('Mot de passe oublié')]
class MotDePasseOublie extends Component
{
    public string $email = '';

    public bool $envoye = false;

    public function envoyerLien(): void
    {
        $this->validate(['email' => ['required', 'email']], attributes: ['email' => 'e-mail']);

        $cleLimite = 'mot-de-passe-oublie:'.request()->ip();

        if (! RateLimiter::tooManyAttempts($cleLimite, 5)) {
            RateLimiter::hit($cleLimite, 15 * 60);
            Password::broker('users')->sendResetLink(['email' => mb_strtolower(trim($this->email))]);
        }

        $this->envoye = true;
    }

    public function render(): View
    {
        return view('livewire.auth.mot-de-passe-oublie');
    }
}
