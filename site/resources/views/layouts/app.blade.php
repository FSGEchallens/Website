<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @php($titrePage = $title ?? trim($__env->yieldContent('titre')))
    <title>{{ $titrePage ? $titrePage.' – ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @auth
        {{-- Applique tout de suite l'état replié du menu de gauche, pour éviter qu'il « saute » au chargement. --}}
        <script>if (localStorage.getItem('menuLateralReplie') === '1') { document.documentElement.classList.add('menu-replie'); }</script>
    @endauth
</head>
<body>
    @include('partials.entete')

    @auth
        <div class="espace">
            @include('partials.menu-lateral')

            <main class="espace__contenu">
                <button type="button" class="ouvrir-menu-mobile" data-bascule-menu-lateral aria-controls="menu-lateral">☰ Mon espace</button>
                @if ($apercu = auth()->user()->roleEnApercu())
                    <form method="POST" action="{{ route('apercu-niveau') }}" class="bandeau-apercu" role="status">
                        @csrf
                        <span>👁 Aperçu du site en tant que <strong>{{ $apercu->libelle() }}</strong> (votre niveau réel n'est pas modifié).</span>
                        <button type="submit" name="niveau" value="{{ auth()->user()->roleReel()->value }}" class="bouton bouton--petit">
                            Revenir à {{ auth()->user()->roleReel()->libelle() }}
                        </button>
                    </form>
                @endif
                @include('partials.message')
                @hasSection('pleine-largeur')
                    <div class="page-dans-espace">@yield('pleine-largeur')</div>
                @else
                    @isset($slot) {{ $slot }} @else @yield('contenu') @endisset
                @endif
            </main>
        </div>
    @else
        @hasSection('pleine-largeur')
            <main>
                <div class="conteneur">@include('partials.message')</div>
                @yield('pleine-largeur')
            </main>
        @else
            <main class="conteneur contenu-public">
                @include('partials.message')
                @isset($slot) {{ $slot }} @else @yield('contenu') @endisset
            </main>
        @endif
    @endauth

    <footer class="pied">
        <div class="conteneur pied__contenu">
            <p>© FSG Echallens - Tous droits réservés</p>
            <a href="{{ config('site.facebook') }}" target="_blank" rel="noopener">Facebook</a>
        </div>
    </footer>
</body>
</html>
