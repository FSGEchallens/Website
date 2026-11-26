<div class="carte carte--etroite">
    <h1>Code de connexion</h1>

    <p>Nous venons de vous envoyer un code à 6 chiffres par e-mail. Il est valable {{ config('auth.code_connexion.validite_minutes') }} minutes.</p>

    @if ($info)
        <div class="alerte alerte--info" role="status">{{ $info }}</div>
    @endif

    <form wire:submit="verifier" novalidate>
        <x-champ nom="code" libelle="Code reçu par e-mail" inputmode="numeric" autocomplete="one-time-code"
                 maxlength="6" autofocus class="code" />

        <div class="actions">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="verifier">Se connecter</span>
                <span wire:loading wire:target="verifier">Vérification…</span>
            </button>
            <button type="button" class="bouton bouton--secondaire" wire:click="renvoyerCode">Renvoyer un code</button>
        </div>
    </form>

    <p class="discret">Pensez à regarder dans vos courriers indésirables (spam). <a href="{{ route('login') }}">Recommencer</a></p>
</div>
