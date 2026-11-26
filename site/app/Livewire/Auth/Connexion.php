<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Notifications\CodeConnexion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Étape 1 de la connexion : e-mail société + mot de passe, puis envoi d'un code par e-mail.
 */
#[Title('Connexion')]
class Connexion extends Component
{
    public const TENTATIVES_MAX = 5;

    public string $email = '';

    public string $password = '';

    public function seConnecter(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], attributes: ['email' => 'e-mail', 'password' => 'mot de passe']);

        $email = mb_strtolower(trim($this->email));
        $cleLimite = 'connexion:'.Str::transliterate($email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($cleLimite, self::TENTATIVES_MAX)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cleLimite) / 60);

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
            ]);
        }

        $user = User::where('email', $email)->first();

        // Hachage factice si le compte n'existe pas, pour ne pas révéler son existence par le temps de réponse.
        $motDePasseCorrect = Hash::check($this->password, $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || $user->password === null || ! $motDePasseCorrect || ! $user->estActif()) {
            RateLimiter::hit($cleLimite, 15 * 60);
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => 'E-mail ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($cleLimite);

        $user->notify(new CodeConnexion($user->genererCodeConnexion()));

        session()->put('connexion', [
            'utilisateur_id' => $user->id,
            'debut' => now()->timestamp,
        ]);

        $this->redirectRoute('connexion.code');
    }

    public function render(): View
    {
        return view('livewire.auth.connexion');
    }
}
