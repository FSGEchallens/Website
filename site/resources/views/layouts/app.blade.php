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
                @include('partials.message')
                @isset($slot) {{ $slot }} @else @yield('contenu') @endisset
            </main>
        </div>
    @else
        <main class="conteneur contenu-public">
            @include('partials.message')
            @isset($slot) {{ $slot }} @else @yield('contenu') @endisset
        </main>
    @endauth
</body>
</html>
