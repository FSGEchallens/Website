<div x-data="protectionModifications" x-on:input="marquerModifie()" x-on:change="marquerModifie()" x-on:submit="avantEnregistrement()">
    <div class="barre">
        <h1>Partenaires</h1>
        <div class="actions">
            <a href="{{ route('accueil') }}#partenaires" class="bouton bouton--secondaire">Voir sur la page d'accueil</a>
            @if ($edition === null)
                <button type="button" class="bouton" wire:click="ajouter">+ Ajouter un partenaire</button>
            @endif
        </div>
    </div>

    @if ($edition !== null)
        <section class="carte">
            <h2>{{ $edition === 0 ? 'Nouveau partenaire' : 'Modifier « '.$nom.' »' }}</h2>
            <form wire:submit="enregistrer" novalidate>
                <div class="identite identite--logo">
                    <div class="logo-partenaire">
                        <div class="logo-partenaire__cadre">
                            @if ($urlApercu)
                                <img src="{{ $urlApercu }}" alt="Aperçu du logo">
                            @else
                                <span class="discret">Pas encore de logo</span>
                            @endif
                        </div>
                        <div wire:loading wire:target="logo" class="discret">Chargement du logo…</div>
                        <label class="bouton bouton--secondaire bouton--petit">
                            {{ $urlApercu ? 'Changer le logo' : 'Choisir le logo' }}
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/gif" class="visuellement-cache">
                        </label>
                        <div class="aide">PNG (idéal, avec fond transparent), JPEG, WebP ou GIF, 5 Mo au maximum.</div>
                        @error('logo') <div class="erreur">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <x-champ nom="nom" libelle="Nom du partenaire" requis />
                        <x-champ nom="url" libelle="Lien vers son site" type="url" placeholder="https://www.exemple.ch" />
                        <label class="case"><input type="checkbox" wire:model="visible"> Afficher sur la page d'accueil</label>
                    </div>
                </div>

                <div class="actions bloc">
                    <button type="submit" class="bouton" wire:loading.attr="disabled" wire:target="logo,enregistrer">Enregistrer</button>
                    <button type="button" class="bouton bouton--secondaire" x-on:click="oublier()" wire:click="annuler">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    <div class="tableau-conteneur">
        <table>
            <thead>
                <tr>
                    <th>Ordre</th>
                    <th>Logo</th>
                    <th>Partenaire</th>
                    <th>Affiché</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partenaires as $partenaire)
                    <tr wire:key="partenaire-{{ $partenaire->id }}" @class(['ligne-masquee' => ! $partenaire->visible])>
                        <td data-label="Ordre">
                            <div class="fleches">
                                <button type="button" class="bouton-icone" wire:click="deplacer({{ $partenaire->id }}, -1)"
                                        @disabled($loop->first) title="Monter" aria-label="Monter {{ $partenaire->nom }}">↑</button>
                                <button type="button" class="bouton-icone" wire:click="deplacer({{ $partenaire->id }}, 1)"
                                        @disabled($loop->last) title="Descendre" aria-label="Descendre {{ $partenaire->nom }}">↓</button>
                            </div>
                        </td>
                        <td data-label="Logo">
                            @if ($partenaire->urlLogo())
                                <img src="{{ $partenaire->urlLogo() }}" alt="" class="logo-liste" loading="lazy">
                            @endif
                        </td>
                        <td data-label="Partenaire">
                            <strong>{{ $partenaire->nom }}</strong>
                            @if ($partenaire->url)
                                <br><a href="{{ $partenaire->url }}" target="_blank" rel="noopener" class="discret">{{ $partenaire->url }}</a>
                            @endif
                        </td>
                        <td data-label="Affiché">
                            <button type="button" class="lien" wire:click="basculerVisibilite({{ $partenaire->id }})">
                                {{ $partenaire->visible ? 'Oui (masquer)' : 'Non (afficher)' }}
                            </button>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="button" class="lien" wire:click="modifier({{ $partenaire->id }})">Modifier</button>
                                <button type="button" class="lien lien--danger" wire:click="supprimer({{ $partenaire->id }})"
                                        wire:confirm="Supprimer définitivement le partenaire « {{ $partenaire->nom }} » ?">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="discret">Aucun partenaire pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
