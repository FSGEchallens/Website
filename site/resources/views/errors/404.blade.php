@extends('layouts.app')

@section('titre', 'Page introuvable')

@section('contenu')
    <div class="carte carte--etroite">
        <h1>Page introuvable</h1>
        <p>Cette page n'existe pas ou a été déplacée.</p>
        <p><a href="{{ route('accueil') }}">Retour à l'accueil</a></p>
    </div>
@endsection
