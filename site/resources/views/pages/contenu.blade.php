{{-- Page simple du site public : un titre, une image facultative et un bloc de texte modifiable par le comité. --}}
@extends('layouts.app')

@section('titre', $titre)

@section('contenu')
    <article class="carte page-contenu">
        <h1>{{ $titre }}</h1>

        <div @class(['page-contenu__corps', 'page-contenu__corps--avec-image' => $image ?? null])>
            @if ($image ?? null)
                <img class="page-contenu__image" src="{{ asset($image) }}" alt="{{ $texteImage ?? '' }}">
            @endif

            <div>
                @if (auth()->user()?->can('gerer-contenu'))
                    <livewire:site.bloc-texte :cle="$cle" presentation="texte" />
                @else
                    @include('partials.bloc-contenu', ['bloc' => $bloc, 'presentation' => 'texte'])
                @endif
            </div>
        </div>
    </article>
@endsection
