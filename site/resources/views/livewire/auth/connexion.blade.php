<div class="carte carte--etroite">
    {{-- Le menu de gauche de l'espace membre s'ouvre à chaque nouvelle connexion. --}}
    <script>localStorage.removeItem('menuLateralReplie');</script>
    <h1>Connexion</h1>

    <form wire:submit="seConnecter" novalidate>
        <x-champ nom="email" libelle="E-mail société" type="email" autocomplete="username" autofocus
                 aide="Votre adresse @gymechallens.ch." />
        <x-champ nom="password" libelle="Mot de passe" type="password" autocomplete="current-password" />

        <div class="actions">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="seConnecter">Continuer</span>
                <span wire:loading wire:target="seConnecter">Vérification…</span>
            </button>
        </div>
    </form>

    <p class="discret">
        Un code de confirmation vous sera ensuite envoyé par e-mail.<br>
        <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
    </p>
</div>
