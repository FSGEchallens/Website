{{-- Page Contact : informations (modifiables par le comité) + formulaire de contact. --}}
@extends('layouts.app')

@section('titre', 'Contact')

@section('contenu')
    <h1>Contact</h1>

    <div class="contact">
        <section class="carte contact__informations" aria-labelledby="titre-informations">
            <h2 id="titre-informations">Informations</h2>
            @if (auth()->user()?->can('gerer-contenu'))
                <livewire:site.bloc-texte cle="contact.informations" presentation="texte" />
            @else
                @include('partials.bloc-contenu', ['bloc' => $informations, 'presentation' => 'texte'])
            @endif
        </section>

        <livewire:site.formulaire-contact />
    </div>
@endsection
