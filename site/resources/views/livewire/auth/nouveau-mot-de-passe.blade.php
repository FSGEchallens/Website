<div class="carte carte--etroite">
    @if ($invitation)
        <h1>Bienvenue !</h1>
        <p>Choisissez le mot de passe de votre compte. Vous l'utiliserez avec votre e-mail société pour vous connecter.</p>
    @else
        <h1>Nouveau mot de passe</h1>
    @endif

    <form wire:submit="enregistrer" novalidate>
        <x-champ nom="email" libelle="E-mail société" type="email" autocomplete="username" />
        <x-champ nom="password" libelle="Nouveau mot de passe" type="password" autocomplete="new-password"
                 aide="Au moins 10 caractères, avec des lettres et des chiffres." />
        <x-champ nom="password_confirmation" libelle="Confirmer le mot de passe" type="password" autocomplete="new-password" />

        <div class="actions">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="enregistrer">Enregistrer le mot de passe</span>
                <span wire:loading wire:target="enregistrer">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
