<?php

namespace App\Livewire\Site;

use App\Mail\MessageContact;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Formulaire de contact, protégé contre le spam SANS service externe :
 * - question en toutes lettres (« Combien font sept plus deux ? »), réponse gardée côté serveur ;
 * - champ piège invisible (rempli par les robots) ;
 * - délai minimal avant l'envoi (les robots envoient instantanément) ;
 * - limite d'envois par adresse IP ;
 * - refus des messages contenant beaucoup de liens.
 */
class FormulaireContact extends Component
{
    public const DELAI_MINIMUM_SECONDES = 3;

    public const ENVOIS_MAX = 3;

    public const LIENS_MAX = 2;

    private const CHIFFRES = [1 => 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];

    private const NOMBRES = [
        2 => 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit',
    ];

    public string $prenom = '';

    public string $nom = '';

    public string $email = '';

    public string $sujet = '';

    public string $message = '';

    public string $reponse = '';

    /** Champ piège : invisible pour les humains, rempli par les robots. */
    public string $site_web = '';

    public string $question = '';

    public bool $envoye = false;

    public function mount(): void
    {
        $this->nouvelleQuestion();
        session()->put('contact.debut', now()->timestamp);
    }

    public function envoyer(): void
    {
        $this->validate([
            'prenom' => ['required', 'string', 'max:80', 'not_regex:/[\r\n<>]/'],
            'nom' => ['required', 'string', 'max:80', 'not_regex:/[\r\n<>]/'],
            'email' => ['required', 'email', 'max:255'],
            'sujet' => ['nullable', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'reponse' => ['required', 'string', 'max:20'],
        ], [
            'prenom.not_regex' => 'Le prénom contient des caractères non autorisés.',
            'nom.not_regex' => 'Le nom contient des caractères non autorisés.',
            'sujet.not_regex' => 'Le sujet ne doit tenir que sur une ligne.',
            'message.min' => 'Le message est trop court.',
            'reponse.required' => 'Répondez à la question anti-robot.',
        ], [
            'prenom' => 'prénom', 'email' => 'adresse e-mail', 'message' => 'message', 'reponse' => 'réponse',
        ]);

        // Robot : le champ piège est rempli. On fait comme si tout s'était bien passé, sans rien envoyer.
        if ($this->site_web !== '') {
            $this->terminer();

            return;
        }

        if (now()->timestamp - (int) session('contact.debut', 0) < self::DELAI_MINIMUM_SECONDES) {
            throw ValidationException::withMessages(['message' => 'Envoi trop rapide. Patientez quelques secondes puis réessayez.']);
        }

        if (! $this->reponseCorrecte()) {
            $this->reset('reponse');
            $this->nouvelleQuestion();

            throw ValidationException::withMessages(['reponse' => 'Réponse incorrecte. Voici une nouvelle question.']);
        }

        if (preg_match_all('#(https?://|www\.)#i', $this->message) > self::LIENS_MAX) {
            throw ValidationException::withMessages(['message' => 'Votre message contient trop de liens. Retirez-en quelques-uns.']);
        }

        $cleLimite = 'contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($cleLimite, self::ENVOIS_MAX)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cleLimite) / 60);

            throw ValidationException::withMessages(['message' => "Vous avez déjà envoyé plusieurs messages. Réessayez dans {$minutes} minute(s)."]);
        }

        RateLimiter::hit($cleLimite, 10 * 60);

        Mail::to(config('site.email_contact'))->send(new MessageContact([
            'prenom' => trim($this->prenom),
            'nom' => trim($this->nom),
            'email' => trim($this->email),
            'sujet' => trim($this->sujet) ?: null,
            'message' => trim($this->message),
        ]));

        $this->terminer();
    }

    public function nouveauMessage(): void
    {
        $this->envoye = false;
        $this->nouvelleQuestion();
        session()->put('contact.debut', now()->timestamp);
    }

    private function terminer(): void
    {
        $this->reset('prenom', 'nom', 'email', 'sujet', 'message', 'reponse', 'site_web');
        $this->resetErrorBag();
        $this->envoye = true;
        session()->forget(['contact.reponse', 'contact.debut']);
    }

    private function nouvelleQuestion(): void
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);

        $this->question = 'Combien font '.self::CHIFFRES[$a].' plus '.self::CHIFFRES[$b].' ?';
        session()->put('contact.reponse', $a + $b);
    }

    /**
     * Accepte « 9 », « neuf », « Neuf. »…
     */
    private function reponseCorrecte(): bool
    {
        $attendue = session('contact.reponse');

        if (! is_int($attendue)) {
            return false;
        }

        $saisie = mb_strtolower(trim($this->reponse, " \t\n\r\0\x0B.!"));

        return $saisie === (string) $attendue || $saisie === (self::NOMBRES[$attendue] ?? null);
    }

    public function render(): View
    {
        return view('livewire.site.formulaire-contact');
    }
}
