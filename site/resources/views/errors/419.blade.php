@extends('layouts.app')

@section('titre', 'Page expirée')

@section('contenu')
    <div class="carte carte--etroite">
        <h1>Page expirée</h1>
        <p>La page est restée ouverte trop longtemps. Rechargez-la et recommencez.</p>
        <p><a href="{{ url()->previous() }}">Revenir à la page précédente</a></p>
    </div>
@endsection
