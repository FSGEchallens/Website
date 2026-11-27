<div>
    <div class="barre">
        <h1>Membres</h1>
        <a href="{{ route('membres.create') }}" class="bouton">+ Nouveau membre</a>
    </div>

    <div class="barre">
        <div class="barre__filtres">
            <input type="search" class="recherche" wire:model.live.debounce.300ms="recherche"
                   placeholder="Rechercher (nom, prénom, localité, e-mail…)" aria-label="Rechercher un membre">
            <select class="recherche" style="max-width: 12rem" wire:model.live="role" aria-label="Filtrer par autorisation">
                <option value="">Toutes les autorisations</option>
                @foreach ($tousLesRoles as $option)
                    <option value="{{ $option->value }}">{{ $option->libelle() }}</option>
                @endforeach
            </select>
            <label class="case">
                <input type="checkbox" wire:model.live="inclureAnciens"> Afficher aussi les anciens membres
            </label>
        </div>
        <span class="discret">{{ $membres->total() }} membre(s)</span>
    </div>

    <div class="tableau-conteneur">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Autorisation</th>
                    <th>Localité</th>
                    <th>Mobile</th>
                    <th>E-mail société</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($membres as $membre)
                    <tr wire:key="membre-{{ $membre->id }}">
                        <td data-label="Nom">
                            <img src="{{ $membre->urlPhoto() }}" alt="" class="vignette" loading="lazy" width="40" height="40">
                            <strong>{{ $membre->nom }}</strong> {{ $membre->prenom }}
                            @if ($membre->comite_membre)
                                @php($infoComite = trim(($membre->comite_fonction ?: 'Fonction non indiquée').' · '.($membre->comite_email ?: 'e-mail société affiché').' · position '.$membre->comite_ordre.' sur la page du comité'))
                                <span class="badge badge--comite-page infobulle" tabindex="0" data-infobulle="{{ $infoComite }}"
                                      aria-label="Fait partie du comité : {{ $infoComite }}">★ Comité</span>
                            @endif
                            @unless ($membre->estActif())
                                <span class="badge">ancien membre</span>
                            @endunless
                        </td>
                        <td data-label="Autorisation">
                            @if ($membre->role)
                                <span class="badge badge--{{ $membre->role->value }}">{{ $membre->role->libelle() }}</span>
                            @endif
                        </td>
                        <td data-label="Localité">{{ trim($membre->npa.' '.$membre->localite) }}</td>
                        <td data-label="Mobile">{{ $membre->mobile }}</td>
                        <td data-label="E-mail société">
                            {{ $membre->email ?? '—' }}
                        </td>
                        <td>
                            @can('modifier-membre', $membre)
                                <a href="{{ route('membres.edit', $membre) }}">Modifier</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="discret">Aucun membre trouvé.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $membres->links('pagination.simple') }}
</div>
