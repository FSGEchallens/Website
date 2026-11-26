<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' – ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <header class="entete">
        <div class="conteneur entete__contenu">
            <a href="{{ url('/') }}" class="entete__titre">{{ config('app.name') }}</a>
            <nav class="entete__menu">
                @auth
                    @can('gerer-membres')
                        <a href="{{ route('membres.index') }}" @class(['actif' => request()->routeIs('membres.*')])>Membres</a>
                    @endcan
                    <span class="entete__utilisateur">{{ auth()->user()->prenom }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="en-ligne">
                        @csrf
                        <button type="submit" class="lien-bouton">Déconnexion</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @class(['actif' => request()->routeIs('login', 'connexion.*')])>Connexion</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="conteneur">
        @if (session('message'))
            <div class="alerte alerte--succes" role="status">{{ session('message') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
