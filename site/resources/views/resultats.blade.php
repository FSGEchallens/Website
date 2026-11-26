{{-- Page publique « Résultats » : du plus récent au plus ancien. --}}
@extends('layouts.app')

@section('titre', 'Résultats')

@section('contenu')
    @php($peutPublier = auth()->user()?->can('publier-resultats') ?? false)

    <div class="barre">
        <h1>Résultats</h1>
        @if ($peutPublier)
            <div class="actions">
                <a href="{{ route('resultats.gestion', ['nouveau' => 1]) }}" class="bouton bouton--secondaire bouton--petit">+ Ajouter un résultat</a>
                <a href="{{ route('resultats.gestion') }}" class="lien">Gérer les résultats</a>
            </div>
        @endif
    </div>

    @forelse ($resultats as $resultat)
        <article class="carte resultat" id="resultat-{{ $resultat->id }}">
            <h2>
                <span class="resultat__date">{{ $resultat->dateLisible() }}</span>
                {{ $resultat->titre }}
            </h2>

            @if ($resultat->contenu)
                <div class="texte-riche">{!! $resultat->contenu !!}</div>
            @endif

            @if ($resultat->photos)
                <div class="galerie">
                    @foreach ($resultat->photos as $index => $photo)
                        <a href="{{ $resultat->urlPhoto($index) }}" target="_blank" rel="noopener" title="Agrandir la photo">
                            <img src="{{ $resultat->urlPhoto($index, miniature: true) }}" alt="{{ $resultat->titre }} – photo {{ $index + 1 }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($peutPublier)
                <a href="{{ route('resultats.gestion', ['modifier' => $resultat->id]) }}" class="bouton bouton--secondaire bouton--petit resultat__modifier">✎ Modifier</a>
            @endif
        </article>
    @empty
        <p class="discret">Aucun résultat publié pour le moment.</p>
    @endforelse

    {{ $resultats->links('pagination.liens') }}
@endsection
