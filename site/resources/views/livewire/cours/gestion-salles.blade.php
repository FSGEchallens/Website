<div>
    <div class="barre">
        <h1>Salles</h1>
        <div class="actions">
            <a href="{{ route('cours.gestion') }}" class="bouton bouton--secondaire">Groupes et cours</a>
            <button type="button" class="bouton" wire:click="ajouter">+ Nouvelle salle</button>
        </div>
    </div>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif

    <div class="salles">
        @forelse ($salles as $salle)
            <article class="salle carte" wire:key="salle-{{ $salle->id }}">
                <div class="salle__photo">
                    @if ($salle->photos)
                        <img src="{{ $salle->urlPhoto(0, miniature: true) }}" alt="Photo de la salle {{ $salle->nom }}" loading="lazy">
                        @if (count($salle->photos) > 1)
                            <span class="salle__nombre-photos">{{ count($salle->photos) }} photos</span>
                        @endif
                    @else
                        <span class="discret">Pas de photo</span>
                    @endif
                </div>
                <div class="salle__texte">
                    <h2>{{ $salle->nom }}</h2>
                    <p>{{ $salle->adresseComplete() ?: 'Adresse à compléter' }}</p>
                    @if ($salle->informations)
                        <p class="discret">{!! nl2br(e($salle->informations)) !!}</p>
                    @endif
                    <p class="discret">
                        @php($u = $utilisations[$salle->id] ?? ['cours' => 0, 'sessions' => 0])
                        Utilisée par {{ $u['cours'] }} cours{{ $u['sessions'] ? ' et '.$u['sessions'].' session(s)' : '' }}
                    </p>
                    <div class="actions">
                        <a href="{{ $salle->urlCarte() }}" target="_blank" rel="noopener">Voir sur Google Maps</a>
                        <button type="button" class="lien" wire:click="modifier({{ $salle->id }})">Modifier</button>
                        <button type="button" class="lien lien--danger" wire:click="supprimer({{ $salle->id }})"
                                wire:confirm="Supprimer la salle « {{ $salle->nom }} » ?">Supprimer</button>
                    </div>
                </div>
            </article>
        @empty
            <p class="discret">Aucune salle pour le moment.</p>
        @endforelse
    </div>

    @if ($edition !== null)
        <div class="fenetre" role="dialog" aria-modal="true" aria-labelledby="titre-fenetre-salle" wire:key="fenetre-salle-{{ $edition }}"
             x-data x-on:keydown.escape.window="$wire.annuler()">
            <div class="fenetre__boite fenetre__boite--large">
                <h3 id="titre-fenetre-salle">{{ $edition ? 'Modifier la salle' : 'Nouvelle salle' }}</h3>

                <form wire:submit="enregistrer" novalidate>
                    <x-champ nom="nom" libelle="Nom" requis placeholder="Ex. Echallens, Court-Champ 3" />

                    <div class="grille grille--adresse">
                        <x-champ nom="rue" libelle="Rue et numéro" modificateur=".blur" placeholder="Ex. Rue du Collège 1" />
                        <x-champ nom="npa" libelle="NPA" modificateur=".blur" inputmode="numeric" maxlength="5" placeholder="1040" />
                        <x-champ nom="localite" libelle="Localité" modificateur=".blur" placeholder="Echallens" />
                    </div>

                    <div @class(['champ', 'champ--erreur' => $errors->has('informations')])>
                        <label for="champ-informations">Informations pratiques</label>
                        <textarea id="champ-informations" rows="2" wire:model="informations" placeholder="Accès, parking, entrée…"></textarea>
                        @error('informations') <div class="erreur">{{ $message }}</div> @enderror
                    </div>

                    <fieldset class="groupe">
                        <legend>Carte Google</legend>
                        @if ($apercu->urlCarteIntegree() && ($rue || $localite))
                            <iframe class="salle__carte" src="{{ $apercu->urlCarteIntegree() }}" title="Carte de la salle" loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade" wire:key="carte-{{ md5($apercu->adresseComplete()) }}"></iframe>
                        @else
                            <p class="discret">La carte s’affiche dès que l’adresse est saisie.</p>
                        @endif
                        <x-champ nom="lien_carte" libelle="Lien Google Maps (facultatif)" type="url" placeholder="https://maps.app.goo.gl/…"
                                 aide="Pour pointer sur une entrée précise : dans Google Maps, « Partager » puis « Copier le lien ». Sans lien, l’adresse est utilisée." />
                    </fieldset>

                    <fieldset class="groupe" x-data="controleFichiers(15, ['jpg', 'jpeg', 'png', 'webp'])" x-on:change.capture="verifier($event)">
                        @php($nombrePhotos = count($photos) + count($nouvellesPhotos))
                        <legend>Photos ({{ $nombrePhotos }} / {{ \App\Models\Salle::PHOTOS_MAX }})</legend>

                        @if ($nombrePhotos > 0)
                            <div class="galerie-edition">
                                @foreach ($photos as $index => $nom)
                                    @php($indexBase = array_search($nom, $salleEditee?->photos ?? [], true))
                                    <figure wire:key="photo-{{ $nom }}">
                                        @if ($indexBase !== false)
                                            <img src="{{ $salleEditee->urlPhoto($indexBase, miniature: true) }}" alt="Photo {{ $index + 1 }}">
                                        @endif
                                        <figcaption>
                                            <button type="button" class="bouton-icone" wire:click="deplacerPhoto({{ $index }}, -1)" @disabled($loop->first) title="Vers la gauche" aria-label="Déplacer vers la gauche">←</button>
                                            <button type="button" class="bouton-icone" wire:click="deplacerPhoto({{ $index }}, 1)" @disabled($loop->last) title="Vers la droite" aria-label="Déplacer vers la droite">→</button>
                                            <button type="button" class="lien lien--danger" wire:click="retirerPhoto({{ $index }})">Retirer</button>
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
                        @else
                            <p class="discret">Pas encore de photo.</p>
                        @endif

                        <div class="actions">
                            @if ($nombrePhotos < \App\Models\Salle::PHOTOS_MAX)
                                <label class="bouton bouton--secondaire bouton--petit">
                                    {{ count($nouvellesPhotos) ? 'Choisir d’autres photos' : 'Ajouter des photos' }}
                                    <input type="file" wire:model="nouvellesPhotos" multiple accept="image/jpeg,image/png,image/webp" class="visuellement-cache">
                                </label>
                            @endif
                            <span wire:loading wire:target="nouvellesPhotos" class="discret">Envoi en cours…</span>
                        </div>
                        <div class="aide">
                            Jusqu’à {{ \App\Models\Salle::PHOTOS_MAX }} photos (JPEG, PNG ou WebP, 15 Mo au maximum chacune). La première est la photo principale.
                            Les photos sont réduites et leurs données GPS sont supprimées.
                        </div>
                        <div class="erreur" x-show="erreurFichier" x-text="erreurFichier" x-cloak role="alert"></div>
                        @error('nouvellesPhotos') <div class="erreur">{{ $message }}</div> @enderror
                        @error('nouvellesPhotos.*') <div class="erreur">{{ $message }}</div> @enderror
                    </fieldset>

                    <div class="actions actions--fenetre">
                        <button type="button" class="bouton bouton--secondaire" wire:click="annuler">Annuler</button>
                        <button type="submit" class="bouton" wire:loading.attr="disabled">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
