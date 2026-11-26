<div class="carte carte--etroite">
    @if ($invitation)
        <h1>Bienvenue !</h1>
        <p>Choisissez le mot de passe de votre compte. Vous l'utiliserez avec votre e-mail société pour vous connecter.</p>
    @else
        <h1>Nouveau mot de passe</h1>
    @endif

    <form wire:submit="enregistrer" novalidate>
        <div class="champ">
            <label for="champ-email">E-mail société</label>
            <input id="champ-email" type="email" value="{{ $email }}" autocomplete="username" readonly class="lecture-seule">
        </div>

        <x-nouveau-mot-de-passe autofocus />

        <div class="actions">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="enregistrer">Enregistrer le mot de passe</span>
                <span wire:loading wire:target="enregistrer">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
