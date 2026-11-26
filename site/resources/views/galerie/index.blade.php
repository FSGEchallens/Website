{{-- Galerie : liste des albums, du plus récent au plus ancien. --}}
@extends('layouts.app')

@section('titre', 'Galerie')

@section('contenu')
    @php($peutGerer = auth()->user()?->can('gerer-galerie') ?? false)

    <div class="barre">
        <h1>Galerie</h1>
        @if ($peutGerer)
            <a href="{{ route('galerie.nouvel-album') }}" class="bouton">+ Nouvel album</a>
        @endif
    </div>

    <div class="albums">
        @forelse ($albums as $album)
            @php($couverture = $album->photoDeCouverture())
            <a href="{{ route('galerie.album', $album) }}" @class(['album-carte', 'album-carte--non-publie' => ! $album->estPublie()])>
                <div class="album-carte__image">
                    @if ($couverture)
                        <img src="{{ $couverture->url(miniature: true) }}" alt="" loading="lazy">
                    @else
                        <span class="discret">Pas encore de photo</span>
                    @endif
                </div>
                <div class="album-carte__texte">
                    <strong>{{ $album->titre }}</strong>
                    <span class="discret">
                        {{ $album->date?->translatedFormat('j F Y') }}{{ $album->date ? ' · ' : '' }}{{ $album->photos_count }} photo{{ $album->photos_count > 1 ? 's' : '' }}
                    </span>
                    @unless ($album->estPublie())
                        <span class="badge">Non publié</span>
                    @endunless
                </div>
            </a>
        @empty
            <p class="discret">Aucun album pour le moment.</p>
        @endforelse
    </div>
@endsection
