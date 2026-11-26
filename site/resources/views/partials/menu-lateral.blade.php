{{-- Menu de gauche de l'espace membre : uniquement les fonctions autorisées pour le niveau de la personne. --}}
@php
    $utilisateur = auth()->user();
    $sections = collect(config('navigation.espace_membre'))
        ->map(fn (array $section) => [
            ...$section,
            'liens' => array_values(array_filter(
                $section['liens'],
                fn (array $lien) => $utilisateur->aAuMoins(\App\Enums\Role::from($lien['role'])),
            )),
        ])
        ->filter(fn (array $section) => $section['liens'] !== []);
    $role = $utilisateur->role ?? \App\Enums\Role::Membre;
@endphp

<div class="voile" data-bascule-menu-lateral aria-hidden="true"></div>

<aside class="menu-lateral" id="menu-lateral" aria-label="Mon espace">
    <button type="button" class="menu-lateral__bascule" data-bascule-menu-lateral aria-controls="menu-lateral"
            aria-expanded="true" title="Masquer / afficher le menu">
        <span class="si-ouvert" aria-hidden="true">«</span>
        <span class="si-replie" aria-hidden="true">»</span>
        <span class="visuellement-cache">Masquer ou afficher le menu</span>
    </button>

    <div class="menu-lateral__contenu">
        <div class="menu-lateral__utilisateur">
            <img src="{{ $utilisateur->urlPhoto() }}" alt="" class="vignette" width="40" height="40">
            <div>
                <strong>{{ $utilisateur->nomComplet() }}</strong><br>
                <span class="badge badge--{{ $role->value }}">{{ $role->libelle() }}</span>
            </div>
        </div>

        <nav aria-label="Fonctions de l'espace membre">
            @foreach ($sections as $section)
                <h2 class="menu-lateral__titre">{{ $section['titre'] }}</h2>
                <ul>
                    @foreach ($section['liens'] as $lien)
                        <li>
                            <a href="{{ route($lien['route']) }}" @class(['actif' => request()->routeIs($lien['actif'])])
                               @if (request()->routeIs($lien['actif'])) aria-current="page" @endif>{{ $lien['libelle'] }}</a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="menu-lateral__deconnexion">
            @csrf
            <button type="submit" class="lien">Déconnexion</button>
        </form>
    </div>
</aside>
