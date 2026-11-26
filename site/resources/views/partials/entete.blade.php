{{-- Barre du haut + en-tête avec le menu du site (navigation publique). --}}
<div class="barre-haut">
    <div class="conteneur barre-haut__contenu">
        @auth
            <span>Bonjour {{ auth()->user()->prenom }}</span>
            <form method="POST" action="{{ route('logout') }}" class="en-ligne">
                @csrf
                <button type="submit" class="lien-blanc">Déconnexion</button>
            </form>
        @else
            <a href="{{ route('login') }}">Connectez-vous ici</a>
        @endauth
    </div>
</div>

<header class="entete">
    <div class="conteneur entete__contenu">
        <a href="{{ route('accueil') }}" class="logo">
            <img src="{{ asset('images/FSG-Echallens_logo.png') }}" alt="FSG Echallens – accueil" width="150" height="70">
        </a>

        <button type="button" class="menu-site__bascule" data-bascule-menu-site aria-controls="menu-site" aria-expanded="false" aria-label="Ouvrir le menu">☰</button>

        <nav class="menu-site" id="menu-site" aria-label="Menu du site">
            <ul>
                @foreach (config('navigation.site') as $element)
                    @if (isset($element['sous_menu']))
                        <li class="menu-site__parent">
                            <a href="#" data-sous-menu aria-haspopup="true">{{ $element['libelle'] }}</a>
                            <ul @class(['menu-site__sous', 'menu-site__sous--large' => $element['large'] ?? false])>
                                @foreach ($element['sous_menu'] as $lien)
                                    <li @class(['menu-site__titre' => $lien['titre'] ?? false])><a href="{{ $lien['url'] }}">{{ $lien['libelle'] }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        @php($url = isset($element['route']) ? route($element['route']) : $element['url'])
                        <li><a href="{{ $url }}" @class(['actif' => isset($element['route']) && request()->routeIs($element['route'])])>{{ $element['libelle'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</header>
