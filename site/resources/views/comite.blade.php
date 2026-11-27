{{-- Page « Le comité » : personnes cochées « Fait partie du comité », dans l'ordre choisi par le comité. --}}
@extends('layouts.app')

@section('titre', 'Le comité')

@section('contenu')
    <h1>Le comité</h1>

    @can('gerer-membres')
        <livewire:site.ordre-comite />
    @else
        <div class="comite">
            @forelse ($membres as $membre)
                <article class="comite-carte">
                    @include('partials.carte-comite', ['membre' => $membre])
                </article>
            @empty
                <p class="discret">La composition du comité sera bientôt publiée.</p>
            @endforelse
        </div>
    @endcan
@endsection
