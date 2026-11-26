<div class="carte carte--etroite">
    <h1>Mot de passe oublié</h1>

    @if ($envoye)
        <div class="alerte alerte--succes" role="status">
            Si un compte existe pour cette adresse, un e-mail avec un lien pour choisir un nouveau mot de passe vient d'être envoyé.
            Le lien est valable 60 minutes. Pensez à regarder dans vos courriers indésirables (spam).
        </div>
        <p><a href="{{ route('login') }}">Retour à la connexion</a></p>
    @else
        <p>Indiquez votre e-mail société : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>

        <form wire:submit="envoyerLien" novalidate>
            <x-champ nom="email" libelle="E-mail société" type="email" autocomplete="username" autofocus />

            <div class="actions">
                <button type="submit" class="bouton">Envoyer le lien</button>
                <a href="{{ route('login') }}" class="bouton bouton--secondaire">Annuler</a>
            </div>
        </form>
    @endif
</div>
