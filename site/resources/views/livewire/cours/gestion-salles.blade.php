<div>
    <div class="barre">
        <h1>Bâtiments et salles</h1>
        <div class="actions">
            <a href="{{ route('cours.gestion') }}" class="bouton bouton--secondaire">Groupes</a>
            <button type="button" class="bouton" wire:click="ajouter">+ Nouveau bâtiment</button>
        </div>
    </div>

    <p class="discret">
        Un <strong>bâtiment</strong> a une adresse, une carte et des photos. Il contient une ou plusieurs <strong>salles</strong>
        (ex. la salle double séparable Court-Champ 3 / 4). Un cours peut utiliser plusieurs salles d’un même bâtiment.
    </p>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif

    <div class="salles">
        @forelse ($batiments as $batiment)
            <article class="salle carte" wire:key="batiment-{{ $batiment->id }}">
                <div class="salle__photo">
                    @if ($batiment->photos)
                        <img src="{{ $batiment->urlPhoto(0, miniature: true) }}" alt="Photo du bâtiment {{ $batiment->nom }}" loading="lazy">
                        @if (count($batiment->photos) > 1)
                            <span class="salle__nombre-photos">{{ count($batiment->photos) }} photos</span>
                        @endif
                    @else
                        <span class="discret">Pas de photo</span>
                    @endif
                </div>
                <div class="salle__texte">
                    <h2>{{ $batiment->nom }}</h2>
                    <p>{{ $batiment->adresseComplete() ?: 'Adresse à compléter' }}</p>
                    @if ($batiment->informations)
                        <p class="discret">{!! nl2br(e($batiment->informations)) !!}</p>
                    @endif
                    <div class="actions">
                        <a href="{{ $batiment->urlCarte() }}" target="_blank" rel="noopener">Voir sur Google Maps</a>
                        <button type="button" class="lien" wire:click="modifier({{ $batiment->id }})">Modifier</button>
                        <button type="button" class="lien lien--danger" wire:click="supprimer({{ $batiment->id }})"
                                wire:confirm="Supprimer le bâtiment « {{ $batiment->nom }} » ?">Supprimer</button>
                    </div>
                </div>

                <div class="salle__salles">
                    <h3>Salles</h3>
                    <ul class="liste-salles">
                        @foreach ($batiment->salles as $salle)
                            @php($u = $utilisations[$salle->id] ?? ['cours' => 0, 'sessions' => 0])
                            <li wire:key="salle-{{ $salle->id }}">
                                @if ($salleRenommee === $salle->id)
                                    <form wire:submit="enregistrerSalle" class="liste-salles__edition">
                                        <input type="text" wire:model="nomSalle" aria-label="Nom de la salle" maxlength="150" autofocus>
                                        <button type="submit" class="bouton bouton--petit">OK</button>
                                        <button type="button" class="lien" wire:click="annulerSalle">Annuler</button>
                                    </form>
                                    @error('nomSalle') <div class="erreur">{{ $message }}</div> @enderror
                                @else
                                    <span class="liste-salles__nom">{{ $salle->nom }}</span>
                                    <span class="discret">{{ $u['cours'] }} cours{{ $u['sessions'] ? ', '.$u['sessions'].' session(s)' : '' }}</span>
                                    <button type="button" class="lien" wire:click="renommerSalle({{ $salle->id }})">Renommer</button>
                                    <button type="button" class="lien lien--danger" wire:click="supprimerSalle({{ $salle->id }})"
                                            wire:confirm="Supprimer la salle « {{ $salle->nom }} » ?">Supprimer</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <form wire:submit="ajouterSalle({{ $batiment->id }})" class="liste-salles__ajout">
                        <input type="text" wire:model="nouvelleSalle.{{ $batiment->id }}" placeholder="Nom de la nouvelle salle" aria-label="Nom de la nouvelle salle" maxlength="150">
                        <button type="submit" class="bouton bouton--secondaire bouton--petit">+ Salle</button>
                    </form>
                    @error('nouvelleSalle.'.$batiment->id) <div class="erreur">{{ $message }}</div> @enderror
                </div>
            </article>
        @empty
            <p class="discret">Aucun bâtiment pour le moment.</p>
        @endforelse
    </div>
    @if ($edition !== null)
        <div class="fenetre" role="dialog" aria-modal="true" aria-labelledby="titre-fenetre-batiment" wire:key="fenetre-batiment-{{ $edition }}"
             x-data x-on:keydown.escape.window="$wire.annuler()">
            <div class="fenetre__boite fenetre__boite--large">
                <h3 id="titre-fenetre-batiment">{{ $edition ? 'Modifier le bâtiment' : 'Nouveau bâtiment' }}</h3>

                <form wire:submit="enregistrer" novalidate>
                    <x-champ nom="nom" libelle="Nom du bâtiment" requis placeholder="Ex. Echallens, Court-Champ" />

                    <div class="grille grille--adresse">
                        <x-champ nom="rue" libelle="Rue et numéro" modificateur=".blur" placeholder="Ex. Rue du Collège 1" />
                        <x-champ nom="npa" libelle="NPA" modificateur=".blur" inputmode="numeric" maxlength="5" placeholder="1040" />
                        <x-champ nom="localite" libelle="Localité" modificateur=".blur" placeholder="Echallens" />
                    </div>

                    @if ($edition === 0)
                        <div @class(['champ', 'champ--erreur' => $errors->has('sallesInitiales')])>
                            <label for="champ-salles-initiales">Salles du bâtiment <span aria-hidden="true">*</span></label>
                            <textarea id="champ-salles-initiales" rows="3" wire:model="sallesInitiales" placeholder="Une salle par ligne, ex. Salle 3"></textarea>
                            <div class="aide">Une salle par ligne. Vous pourrez en ajouter ou en renommer ensuite.</div>
                            @error('sallesInitiales') <div class="erreur">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div @class(['champ', 'champ--erreur' => $errors->has('informations')])>
                        <label for="champ-informations">Informations pratiques</label>
                        <textarea id="champ-informations" rows="2" wire:model="informations" placeholder="Accès, parking, entrée…"></textarea>
                        @error('informations') <div class="erreur">{{ $message }}</div> @enderror
                    </div>

                    <fieldset class="groupe">
                        <legend>Carte Google</legend>
                        @if ($apercu->urlCarteIntegree() && ($rue || $localite))
                            <iframe class="salle__carte" src="{{ $apercu->urlCarteIntegree() }}" title="Carte du bâtiment" loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade" wire:key="carte-{{ md5($apercu->adresseComplete()) }}"></iframe>
                        @else
                            <p class="discret">La carte s’affiche dès que l’adresse est saisie.</p>
                        @endif
                        <x-champ nom="lien_carte" libelle="Lien Google Maps (facultatif)" type="url" placeholder="https://maps.app.goo.gl/…"
                                 aide="Pour pointer sur une entrée précise : dans Google Maps, « Partager » puis « Copier le lien ». Sans lien, l’adresse est utilisée." />
                    </fieldset>

                    <fieldset class="groupe" x-data="controleFichiers(15, ['jpg', 'jpeg', 'png', 'webp'])" x-on:change.capture="verifier($event)">
                        @php($nombrePhotos = count($photos) + count($nouvellesPhotos))
                        <legend>Photos ({{ $nombrePhotos }} / {{ \App\Models\Batiment::PHOTOS_MAX }})</legend>

                        @if ($nombrePhotos > 0)
                            <div class="galerie-edition">
                                @foreach ($photos as $index => $nom)
                                    @php($indexBase = array_search($nom, $batimentEdite?->photos ?? [], true))
                                    <figure wire:key="photo-{{ $nom }}">
                                        @if ($indexBase !== false)
                                            <img src="{{ $batimentEdite->urlPhoto($indexBase, miniature: true) }}" alt="Photo {{ $index + 1 }}">
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
                            @if ($nombrePhotos < \App\Models\Batiment::PHOTOS_MAX)
                                <label class="bouton bouton--secondaire bouton--petit">
                                    {{ count($nouvellesPhotos) ? 'Choisir d’autres photos' : 'Ajouter des photos' }}
                                    <input type="file" wire:model="nouvellesPhotos" multiple accept="image/jpeg,image/png,image/webp" class="visuellement-cache">
                                </label>
                            @endif
                            <span wire:loading wire:target="nouvellesPhotos" class="discret">Envoi en cours…</span>
                        </div>
                        <div class="aide">
                            Jusqu’à {{ \App\Models\Batiment::PHOTOS_MAX }} photos (JPEG, PNG ou WebP, 15 Mo au maximum chacune). La première est la photo principale.
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
