<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        body { font-family: "Open Sans", Arial, sans-serif; margin: 0; color: #333; background: #f7f7f7; }
        header { background: #5d9733; color: #fff; padding: 1.5rem; text-align: center; }
        main { max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
        .carte { background: #fff; border-top: 4px solid #81d742; padding: 1.5rem; border-radius: 4px; }
        .ok { color: #5d9733; font-weight: bold; }
        a { color: #2ea3f2; }
    </style>
</head>
<body>
    <header>
        <h1>{{ config('app.name') }}</h1>
    </header>
    <main>
        <div class="carte">
            <p class="ok">La nouvelle application fonctionne.</p>
            <p>Environnement : <strong>{{ app()->environment() }}</strong></p>
            <p>Base de données : <strong>{{ $baseOk ? 'connectée' : 'non connectée' }}</strong></p>
            @if (session('message'))
                <p class="ok">{{ session('message') }}</p>
            @endif
            @auth
                <p>Connecté : <strong>{{ auth()->user()->nomComplet() }}</strong> – <a href="{{ route('profil') }}">Mon profil</a></p>
                @can('gerer-membres')
                    <p><a href="{{ route('membres.index') }}">Gérer les membres</a></p>
                @endcan
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Déconnexion</button>
                </form>
            @else
                <p><a href="{{ route('login') }}">Se connecter</a></p>
            @endauth
        </div>
    </main>
</body>
</html>
