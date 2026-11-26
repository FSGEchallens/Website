<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Notifications\CodeConnexion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Étape 2 de la connexion : saisie du code à 6 chiffres reçu par e-mail.
 */
#[Title('Code de connexion')]
class SaisieCode extends Component
{
    /** Durée maximale entre l'étape 1 et l'étape 2. */
    public const DUREE_MAX_MINUTES = 15;

    public string $code = '';

    public ?string $info = null;

    public function mount(): void
    {
        if ($this->utilisateurEnAttente() === null) {
            $this->redirectRoute('login');
        }
    }

    public function verifier(): void
    {
        $this->validate(
            ['code' => ['required', 'regex:/^\s*[0-9]{6}\s*$/']],
            ['code.regex' => 'Le code doit contenir 6 chiffres.'],
        );

        $user = $this->utilisateurEnAttente();

        if ($user === null) {
            $this->recommencer('Votre demande de connexion a expiré. Veuillez recommencer.');

            return;
        }

        if (! $user->aUnCodeConnexionValide()) {
            $this->reset('code');

            throw ValidationException::withMessages([
                'code' => 'Ce code a expiré ou a été trop souvent mal saisi. Demandez un nouveau code.',
            ]);
        }

        if (! $user->verifierCodeConnexion($this->code)) {
            $this->reset('code');

            throw ValidationException::withMessages([
                'code' => 'Code incorrect.',
            ]);
        }

        session()->forget('connexion');
        Auth::login($user);
        session()->regenerate();

        $user->forceFill([
            'derniere_connexion_le' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->redirectIntended(default: route('accueil'));
    }

    public function renvoyerCode(): void
    {
        $user = $this->utilisateurEnAttente();

        if ($user === null) {
            $this->recommencer('Votre demande de connexion a expiré. Veuillez recommencer.');

            return;
        }

        $cleLimite = 'renvoi-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($cleLimite, 3)) {
            $this->info = 'Plusieurs codes ont déjà été envoyés. Patientez quelques minutes avant de redemander.';

            return;
        }

        RateLimiter::hit($cleLimite, 10 * 60);

        $user->notify(new CodeConnexion($user->genererCodeConnexion()));

        $this->reset('code');
        $this->resetErrorBag();
        $this->info = 'Un nouveau code vous a été envoyé.';
    }

    private function utilisateurEnAttente(): ?User
    {
        $attente = session('connexion');

        if (! is_array($attente) || now()->timestamp - ($attente['debut'] ?? 0) > self::DUREE_MAX_MINUTES * 60) {
            return null;
        }

        return User::find($attente['utilisateur_id'] ?? null);
    }

    private function recommencer(string $message): void
    {
        session()->forget('connexion');
        session()->flash('message', $message);
        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.saisie-code');
    }
}
