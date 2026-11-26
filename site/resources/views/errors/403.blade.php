@extends('layouts.app')

@section('titre', 'Accès refusé')

@section('contenu')
    <div class="carte carte--etroite">
        <h1>Accès refusé</h1>
        <p>Votre niveau d'autorisation ne permet pas d'afficher cette page.</p>
        <p><a href="{{ route('accueil') }}">Retour à l'accueil</a>@auth · <a href="{{ route('profil') }}">Mon profil</a>@endauth</p>
    </div>
@endsection
