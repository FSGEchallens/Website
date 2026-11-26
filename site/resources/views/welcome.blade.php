@extends('layouts.app')

@section('titre', 'Accueil')

@section('contenu')
    <div class="carte">
        <h1>Bienvenue sur le nouveau site de la FSG Echallens</h1>
        <p>Ce site est en construction. Les pages publiques arriveront prochainement.</p>
        <p class="discret">Environnement : {{ app()->environment() }} · Base de données : {{ $baseOk ? 'connectée' : 'non connectée' }}</p>
        @guest
            <p><a href="{{ route('login') }}" class="bouton">Se connecter</a></p>
        @endguest
    </div>
@endsection
