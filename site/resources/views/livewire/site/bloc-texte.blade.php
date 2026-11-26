<div @class([
        'bloc-editable',
        'recrute' => $presentation === 'recrute',
        'bloc-editable--masque' => ! $bloc->visible,
    ])
    x-data="protectionModifications" x-on:input="marquerModifie()" x-on:submit="avantEnregistrement()">

    @if ($enEdition)
        <form wire:submit="enregistrer" novalidate class="bloc-editable__formulaire">
            <p class="bloc-editable__etiquette">Modifier : {{ $bloc->libelle }}</p>

            @if ($bloc->avec_titre)
                <x-champ nom="titre" libelle="Titre" requis />
            @endif

            <x-editeur-texte nom="contenu" :contenu="$contenu" />

            @if ($bloc->masquable)
                <label class="case"><input type="checkbox" wire:model="visible"> Afficher sur la page d'accueil</label>
            @endif

            <div class="actions bloc-editable__actions">
                <button type="submit" class="bouton bouton--petit">Enregistrer</button>
                <button type="button" class="bouton bouton--secondaire bouton--petit" x-on:click="oublier()" wire:click="annuler">Annuler</button>
            </div>
        </form>
    @else
        <div class="bloc-editable__outils">
            @unless ($bloc->visible)
                <span class="badge">Masqué pour les visiteurs</span>
            @endunless
            @if ($confirmation)
                <span class="discret" role="status">{{ $confirmation }}</span>
            @endif
            <button type="button" class="bouton bouton--secondaire bouton--petit" wire:click="modifier">✎ Modifier</button>
            @if ($bloc->masquable)
                <button type="button" class="lien" wire:click="basculerVisibilite">{{ $bloc->visible ? 'Masquer' : 'Afficher' }}</button>
            @endif
        </div>

        @include('partials.bloc-contenu', ['bloc' => $bloc, 'presentation' => $presentation])
    @endif
</div>
