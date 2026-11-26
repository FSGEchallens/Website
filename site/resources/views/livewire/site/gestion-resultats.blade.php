<div x-data="protectionModifications" x-on:input="marquerModifie()" x-on:change="marquerModifie()" x-on:submit="avantEnregistrement()">
    <div class="barre">
        <h1>Résultats</h1>
        <div class="actions">
            <a href="{{ route('resultats.index') }}" class="bouton bouton--secondaire">Voir la page publique</a>
            @if ($edition === null)
                <button type="button" class="bouton" wire:click="ajouter">+ Ajouter un résultat</button>
            @endif
        </div>
    </div>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif

    @if ($edition !== null)
        <section class="carte" wire:key="formulaire-{{ $edition }}">
            <h2>{{ $edition === 0 ? 'Nouveau résultat' : 'Modifier le résultat' }}</h2>

            <form wire:submit="enregistrer" novalidate>
                <div class="grille">
                    <x-champ nom="date" libelle="Date du concours" type="date" requis />
                    <x-champ nom="date_fin" libelle="Date de fin" type="date" aide="Seulement si le concours dure plusieurs jours." />
                </div>
                <x-champ nom="titre" libelle="Titre" requis placeholder="Ex. Coupe de Noël aux agrès à Vevey" />

                <div class="bloc">
                    <label class="libelle-bloc">Texte (classements, commentaires…)</label>
                    <x-editeur-texte nom="contenu" :contenu="$contenu" />
                </div>

                <fieldset class="groupe" x-data="controleFichiers(15, ['jpg', 'jpeg', 'png', 'webp'])" x-on:change.capture="verifier($event)">
                    <legend>Photos ({{ count($photos) + count($nouvellesPhotos) }} / {{ \App\Models\Resultat::PHOTOS_MAX }})</legend>

                    <div class="galerie-edition">
                        @foreach ($photos as $index => $nom)
                            @php($indexBase = array_search($nom, $resultatEdite?->photos ?? [], true))
                            <figure wire:key="photo-{{ $nom }}">
                                @if ($indexBase !== false)
                                    <img src="{{ $resultatEdite->urlPhoto($indexBase, miniature: true) }}" alt="Photo {{ $index + 1 }}">
                                @endif
                                <figcaption>
                                    <button type="button" class="bouton-icone" wire:click="deplacerPhoto({{ $index }}, -1)" @disabled($loop->first) title="Vers la gauche" aria-label="Déplacer vers la gauche">←</button>
                                    <button type="button" class="bouton-icone" wire:click="deplacerPhoto({{ $index }}, 1)" @disabled($loop->last) title="Vers la droite" aria-label="Déplacer vers la droite">→</button>
                                    <button type="button" class="lien lien--danger" wire:click="retirerPhoto({{ $index }})" x-on:click="marquerModifie()">Retirer</button>
                                </figcaption>
                            </figure>
                        @endforeach

                        @foreach ($nouvellesPhotos as $index => $fichier)
                            <figure wire:key="nouvelle-{{ $index }}" class="galerie-edition__nouvelle">
                                @if (! $errors->has('nouvellesPhotos.'.$index))
                                    <img src="{{ $fichier->temporaryUrl() }}" alt="Nouvelle photo {{ $index + 1 }}">
                                @endif
                                <figcaption>
                                    <span class="badge">nouvelle</span>
                                    <button type="button" class="lien lien--danger" wire:click="retirerNouvellePhoto({{ $index }})">Retirer</button>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>

                    @if (count($photos) + count($nouvellesPhotos) < \App\Models\Resultat::PHOTOS_MAX)
                        <label class="bouton bouton--secondaire bouton--petit">
                            + Ajouter des photos
                            <input type="file" wire:model="nouvellesPhotos" accept="image/jpeg,image/png,image/webp" multiple class="visuellement-cache">
                        </label>
                        <span wire:loading wire:target="nouvellesPhotos" class="discret">Envoi des photos…</span>
                    @endif
                    <div class="aide">JPEG, PNG ou WebP, 15 Mo au maximum par photo. Elles sont réduites automatiquement.</div>
                    <div class="erreur" x-show="erreurFichier" x-text="erreurFichier" x-cloak role="alert"></div>
                    @error('nouvellesPhotos') <div class="erreur">{{ $message }}</div> @enderror
                    @foreach ($errors->get('nouvellesPhotos.*') as $messages)
                        <div class="erreur">{{ $messages[0] }}</div>
                    @endforeach
                </fieldset>

                <label class="case"><input type="checkbox" wire:model="visible"> Publié sur le site (décocher pour le masquer)</label>

                <div class="actions bloc">
                    <button type="submit" class="bouton" wire:loading.attr="disabled" wire:target="nouvellesPhotos,enregistrer">Enregistrer</button>
                    <button type="button" class="bouton bouton--secondaire" x-on:click="oublier()" wire:click="annuler">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    <div class="barre">
        <input type="search" class="recherche" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un résultat (titre, lieu…)" aria-label="Rechercher">
        <span class="discret">{{ $resultats->total() }} résultat(s)</span>
    </div>

    <div class="tableau-conteneur">
        <table>
            <thead>
                <tr><th>Date</th><th>Titre</th><th>Photos</th><th>État</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($resultats as $resultat)
                    <tr wire:key="resultat-{{ $resultat->id }}" @class(['ligne-masquee' => ! $resultat->estAffichable()])>
                        <td data-label="Date">{{ $resultat->dateLisible() }}</td>
                        <td data-label="Titre"><strong>{{ $resultat->titre }}</strong></td>
                        <td data-label="Photos">{{ count($resultat->photos ?? []) }}</td>
                        <td data-label="État">{{ $resultat->estAffichable() ? 'Publié' : 'Masqué' }}</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="lien" wire:click="modifier({{ $resultat->id }})">Modifier</button>
                                <button type="button" class="lien" wire:click="basculerVisibilite({{ $resultat->id }})">{{ $resultat->estAffichable() ? 'Masquer' : 'Publier' }}</button>
                                <button type="button" class="lien lien--danger" wire:click="supprimer({{ $resultat->id }})"
                                        wire:confirm="Supprimer le résultat « {{ $resultat->titre }} » et ses photos ?">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="discret">Aucun résultat pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $resultats->links('pagination.simple') }}
</div>
