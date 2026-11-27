<div>
    <div class="actions bloc">
        <button type="button" class="bouton bouton--secondaire bouton--petit" wire:click="basculerEdition">
            {{ $enEdition ? '✓ Terminé' : '✎ Modifier l’ordre' }}
        </button>
        <a href="{{ route('membres.index') }}" class="lien">Ajouter ou retirer une personne (fiche membre → « Comité »)</a>
    </div>

    <div class="comite">
        @forelse ($membres as $membre)
            <article class="comite-carte" wire:key="comite-{{ $membre->id }}">
                @include('partials.carte-comite', ['membre' => $membre])

                @if ($enEdition)
                    <div class="comite-carte__ordre">
                        <button type="button" class="bouton-icone" wire:click="deplacer({{ $membre->id }}, -1)" @disabled($loop->first)
                                title="Monter" aria-label="Monter {{ $membre->nomComplet() }}">↑</button>
                        <button type="button" class="bouton-icone" wire:click="deplacer({{ $membre->id }}, 1)" @disabled($loop->last)
                                title="Descendre" aria-label="Descendre {{ $membre->nomComplet() }}">↓</button>
                    </div>
                @endif
            </article>
        @empty
            <p class="discret">Personne n'est encore indiqué comme membre du comité. Cochez « Fait partie du comité » dans la fiche des personnes concernées.</p>
        @endforelse
    </div>
</div>
