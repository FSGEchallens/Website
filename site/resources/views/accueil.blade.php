@extends('layouts.app')

@section('titre', 'Accueil')

@section('pleine-largeur')
    <section class="bandeau">
        <div class="conteneur">
            <h1>Bienvenue sur le site de la FSG Echallens</h1>
            <p>Gymnastique de 3 à 99 ans, et volley, depuis 1921.</p>
            <a class="bouton-blanc" href="{{ config('site.inscription') }}">S'inscrire</a>
        </div>
    </section>

    <section class="conteneur accueil">
        <div class="accueil__texte">
            <p>La FSG Echallens, anciennement GYM de la Bruyère fut fondée en 1921. Elle est aujourd’hui membre de l’ACVG (Association Cantonale Vaudoise de Gymnastique), ainsi que de la FSG (Fédération Suisse de Gymnastique). Nous proposons des cours de gymnastique de 3 à 99 ans, ainsi que des cours de volley.</p>
            <p>Nos groupes participent à divers concours que ce soit régionaux ou cantonaux tout au long de la saison, et tous les 5 ans nous participons à la cantonale de gymnastique. Nous participons à diverses manifestations locales sur Echallens et la société organise bisannuellement un spectacle où tous ses membres peuvent montrer à leur famille et amis le résultat de leur labeur.</p>
            <p class="encart">Venez découvrir nos cours et contactez nos moniteurs pour profiter de vos <strong>3 cours d’essai gratuits</strong>. La FSG Echallens, l’essayer c’est l’adopter !</p>
            <img class="accueil__animation" src="{{ asset('images/accueil/animation-accueil.gif') }}" alt="">
        </div>

        <aside class="recrute">
            <h2>La FSG Echallens recrute !</h2>
            <p>La FSG Echallens recherche un moniteur pour son groupe Polysport kids. En cas d’intérêt, merci de contacter Marie Carvalhais sur <a href="mailto:presidence@gymechallens.ch">presidence@gymechallens.ch</a>.</p>
        </aside>
    </section>

    <section class="partenaires" id="partenaires">
        <div class="conteneur">
            <div class="partenaires__titre">
                <h2>Nos partenaires</h2>
                @can('gerer-contenu')
                    <a href="{{ route('partenaires.index') }}" class="bouton bouton--secondaire bouton--petit">✎ Modifier les partenaires</a>
                @endcan
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
