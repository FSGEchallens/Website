@extends('layouts.app')

@section('titre', 'Statuts')

@section('contenu')
    <article class="carte page-contenu">
        <div class="barre">
            <h1>Statuts</h1>
            @if ($existe)
                <a class="bouton" href="{{ route('societe.statuts.pdf', ['v' => $version, 'telecharger' => 1]) }}">Télécharger le PDF</a>
            @endif
        </div>

        @if (session('statuts_remplaces'))
            <div class="alerte alerte--succes" role="status">Les statuts ont été remplacés.</div>
        @endif

        @can('gerer-contenu')
            <livewire:site.document-statuts />
        @endcan

        @if ($existe)
            <object class="statuts-pdf" data="{{ route('societe.statuts.pdf', ['v' => $version]) }}" type="application/pdf">
                <p>
                    Votre navigateur ne peut pas afficher le PDF dans la page.
                    <a href="{{ route('societe.statuts.pdf', ['v' => $version]) }}">Ouvrir les statuts</a>
                </p>
            </object>
        @else
            <p class="discret">Les statuts seront bientôt disponibles.</p>
        @endif
    </article>
@endsection
