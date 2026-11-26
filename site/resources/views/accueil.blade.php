@extends('layouts.app')

@section('titre', 'Accueil')

@php
    $peutModifier = auth()->user()?->can('gerer-contenu') ?? false;
    $recrute = $blocs['accueil.recrute'] ?? null;
    $afficherRecrute = $recrute !== null && ($recrute->visible || $peutModifier);
@endphp

@section('pleine-largeur')
    <section class="bandeau">
        <div class="conteneur">
            <h1>Bienvenue sur le site de la FSG Echallens</h1>
            <p>Gymnastique de 3 à 99 ans, et volley, depuis 1921.</p>
            <a class="bouton-blanc" href="{{ config('site.inscription') }}">S'inscrire</a>
        </div>
    </section>

    @include('partials.annonces')

    <section @class(['conteneur', 'accueil', 'accueil--une-colonne' => ! $afficherRecrute])>
        <div class="accueil__texte">
            @foreach (['accueil.texte' => 'texte', 'accueil.encart' => 'encart'] as $cle => $presentation)
                @isset($blocs[$cle])
                    @if ($peutModifier)
                        <livewire:site.bloc-texte :cle="$cle" :presentation="$presentation" :key="$cle" />
                    @else
                        @include('partials.bloc-contenu', ['bloc' => $blocs[$cle], 'presentation' => $presentation])
                    @endif
                @endisset
            @endforeach
        </div>

        @if ($afficherRecrute)
            @if ($peutModifier)
                <livewire:site.bloc-texte cle="accueil.recrute" presentation="recrute" key="accueil.recrute" />
            @else
                <aside class="recrute">
                    @include('partials.bloc-contenu', ['bloc' => $recrute, 'presentation' => 'recrute'])
                </aside>
            @endif
        @endif
    </section>

    <section class="partenaires" id="partenaires">
        <div class="conteneur">
            <div class="partenaires__titre">
                <h2>Nos partenaires</h2>
                @if ($peutModifier)
                    <a href="{{ route('partenaires.index') }}" class="bouton bouton--secondaire bouton--petit">✎ Modifier les partenaires</a>
                @endif
            </div>
            <div class="partenaires__grille">
                @foreach ($partenaires as $partenaire)
                    <a @if ($partenaire->url) href="{{ $partenaire->url }}" target="_blank" rel="noopener" @endif>
                        @if ($partenaire->urlLogo())
                            <img src="{{ $partenaire->urlLogo() }}" alt="" loading="lazy">
                        @endif
                        <span>{{ $partenaire->nom }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
