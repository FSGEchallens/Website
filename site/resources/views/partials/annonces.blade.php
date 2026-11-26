{{-- Annonces sous le bandeau de la page d'accueil. --}}
@if ($annonces->isNotEmpty() || $peutModifier)
    <section class="conteneur annonces" aria-label="Annonces">
        @foreach ($annonces as $annonce)
            @php($fond = $annonce->image && $annonce->position_image === 'fond')
            <article @class([
                    'annonce',
                    'annonce--image-'.$annonce->position_image => $annonce->image,
                ])
                @if ($fond) style="background-image: linear-gradient(rgba(0, 0, 0, .55), rgba(0, 0, 0, .55)), url('{{ $annonce->urlImage() }}')" @endif>
                @if ($annonce->image && ! $fond)
                    <img class="annonce__image" src="{{ $annonce->urlImage() }}" alt="" loading="lazy">
                @endif

                <div class="annonce__corps">
                    <h2>{{ $annonce->titre }}</h2>
                    @if ($annonce->contenu)
                        <div class="texte-riche">{!! $annonce->contenu !!}</div>
                    @endif

                    @if ($annonce->liens)
                        <div class="annonce__liens">
                            @foreach ($annonce->liens as $index => $lien)
                                <a href="{{ $annonce->urlLien($index) }}" class="bouton" target="_blank" rel="noopener">
                                    {{ $lien['type'] === 'fichier' ? '📄 ' : '' }}{{ $lien['libelle'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($peutModifier)
                        <a href="{{ route('annonces.index', ['modifier' => $annonce->id]) }}" class="bouton bouton--secondaire bouton--petit annonce__modifier">✎ Modifier</a>
                    @endif
                </div>
            </article>
        @endforeach

        @if ($peutModifier)
            <div class="annonces__gestion">
                <a href="{{ route('annonces.index', ['nouvelle' => 1]) }}" class="bouton bouton--secondaire bouton--petit">+ Ajouter une annonce</a>
                <a href="{{ route('annonces.index') }}" class="lien">Gérer les annonces (masquées, programmées…)</a>
            </div>
        @endif
    </section>
@endif
