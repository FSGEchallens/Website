<div x-data="protectionModifications" x-on:input="marquerModifie()" x-on:change="marquerModifie()" x-on:submit="avantEnregistrement()">
    <div class="barre">
        <h1>Annonces</h1>
        <div class="actions">
            <a href="{{ route('accueil') }}" class="bouton bouton--secondaire">Voir la page d'accueil</a>
            @if ($edition === null)
                <button type="button" class="bouton" wire:click="ajouter">+ Nouvelle annonce</button>
            @endif
        </div>
    </div>

    <p class="discret">Les annonces s'affichent sur la page d'accueil, juste sous le bandeau vert. Sans dates, une annonce reste affichée jusqu'à ce qu'on la masque.</p>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif

    @if ($edition !== null)
        <section class="carte" wire:key="formulaire-{{ $edition }}">
            <h2>{{ $edition === 0 ? 'Nouvelle annonce' : 'Modifier l\'annonce' }}</h2>

            <form wire:submit="enregistrer" novalidate>
                <x-champ nom="titre" libelle="Titre" requis placeholder="Ex. Venez fêter nos 120 ans !" />

                <div class="bloc">
                    <label class="libelle-bloc">Texte</label>
                    <x-editeur-texte nom="contenu" :contenu="$contenu" />
                </div>

                <fieldset class="groupe">
                    <legend>Image (facultative)</legend>
                    <div class="identite identite--logo">
                        <div class="logo-partenaire">
                            <div class="logo-partenaire__cadre annonce-apercu">
                                @if ($urlApercu)
                                    <img src="{{ $urlApercu }}" alt="Aperçu de l'image">
                                @else
                                    <span class="discret">Pas d'image</span>
                                @endif
                            </div>
                            <div wire:loading wire:target="image" class="discret">Chargement de l'image…</div>
                            <label class="bouton bouton--secondaire bouton--petit">
                                {{ $urlApercu ? 'Changer l\'image' : 'Choisir une image' }}
                                <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="visuellement-cache">
                            </label>
                            @if ($urlApercu)
                                <button type="button" class="lien" wire:click="retirerLImage" x-on:click="marquerModifie()">Retirer l'image</button>
                            @endif
                            @error('image') <div class="erreur">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <p class="libelle-bloc">Position de l'image</p>
                            @foreach ($positions as $valeur => $libelle)
                                <label class="case"><input type="radio" wire:model="position_image" value="{{ $valeur }}"> {{ $libelle }}</label>
                            @endforeach
                            <div class="aide">« En fond » : l'image remplit l'annonce, assombrie, et le texte s'affiche en blanc par-dessus.</div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="groupe">
                    <legend>Boutons (liens ou fichiers à télécharger)</legend>
                    @foreach ($liens as $index => $lien)
                        <div class="lien-annonce" wire:key="lien-{{ $index }}">
                            <div class="grille">
                                <x-champ :nom="'liens.'.$index.'.libelle'" libelle="Texte du bouton" placeholder="Ex. Réserver mes places" />
                                <div class="champ">
                                    <label for="type-{{ $index }}">Type</label>
                                    <select id="type-{{ $index }}" wire:model.live="liens.{{ $index }}.type">
                                        <option value="url">Lien vers un site</option>
                                        <option value="fichier">Fichier joint (PDF, image)</option>
                                    </select>
                                </div>
                                @if ($lien['type'] === 'url')
                                    <x-champ :nom="'liens.'.$index.'.url'" libelle="Adresse" type="url" placeholder="https://…" />
                                @else
                                    <div @class(['champ', 'champ--erreur' => $errors->has('fichiersLiens.'.$index)])>
                                        <label>Fichier</label>
                                        @if (! empty($lien['fichier']) && empty($fichiersLiens[$index]))
                                            <div class="aide">Actuel : {{ $lien['nom_original'] ?? $lien['fichier'] }}</div>
                                        @endif
                                        <input type="file" wire:model="fichiersLiens.{{ $index }}" accept="application/pdf,image/jpeg,image/png">
                                        <div wire:loading wire:target="fichiersLiens.{{ $index }}" class="discret">Envoi du fichier…</div>
                                        @error('fichiersLiens.'.$index) <div class="erreur">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                            </div>
                            <button type="button" class="lien lien--danger" wire:click="retirerLien({{ $index }})" x-on:click="marquerModifie()">Retirer ce bouton</button>
                        </div>
                    @endforeach
                    @if (count($liens) < \App\Livewire\Site\GestionAnnonces::LIENS_MAX)
                        <button type="button" class="bouton bouton--secondaire bouton--petit" wire:click="ajouterLien" x-on:click="marquerModifie()">+ Ajouter un bouton</button>
                    @endif
                </fieldset>

                <fieldset class="groupe">
                    <legend>Affichage</legend>
                    <div class="grille">
                        <x-champ nom="debut_le" libelle="Afficher à partir du" type="date" aide="Facultatif : vide = tout de suite." />
                        <x-champ nom="fin_le" libelle="Afficher jusqu'au (inclus)" type="date" aide="Facultatif : vide = sans fin." />
                    </div>
                    <label class="case"><input type="checkbox" wire:model="visible"> Annonce active (décocher pour la masquer)</label>
                </fieldset>

                <div class="actions bloc">
                    <button type="submit" class="bouton" wire:loading.attr="disabled" wire:target="image,fichiersLiens,enregistrer">Enregistrer</button>
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
                    <th>Image</th>
                    <th>Annonce</th>
                    <th>État</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($annonces as $annonce)
                    <tr wire:key="annonce-{{ $annonce->id }}" @class(['ligne-masquee' => ! $annonce->estActive()])>
                        <td data-label="Ordre">
                            <div class="fleches">
                                <button type="button" class="bouton-icone" wire:click="deplacer({{ $annonce->id }}, -1)" @disabled($loop->first) title="Monter" aria-label="Monter">↑</button>
                                <button type="button" class="bouton-icone" wire:click="deplacer({{ $annonce->id }}, 1)" @disabled($loop->last) title="Descendre" aria-label="Descendre">↓</button>
                            </div>
                        </td>
                        <td data-label="Image">
                            @if ($annonce->urlImage())
                                <img src="{{ $annonce->urlImage() }}" alt="" class="logo-liste" loading="lazy">
                            @endif
                        </td>
                        <td data-label="Annonce">
                            <strong>{{ $annonce->titre }}</strong>
                            @if ($annonce->debut_le || $annonce->fin_le)
                                <br><span class="discret">
                                    {{ $annonce->debut_le ? 'du '.$annonce->debut_le->format('d.m.Y') : '' }}
                                    {{ $annonce->fin_le ? 'au '.$annonce->fin_le->format('d.m.Y') : '' }}
                                </span>
                            @endif
                        </td>
                        <td data-label="État">{{ $annonce->etat() }}</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="lien" wire:click="modifier({{ $annonce->id }})">Modifier</button>
                                <button type="button" class="lien" wire:click="basculerVisibilite({{ $annonce->id }})">{{ $annonce->visible ? 'Masquer' : 'Activer' }}</button>
                                <button type="button" class="lien lien--danger" wire:click="supprimer({{ $annonce->id }})"
                                        wire:confirm="Supprimer l'annonce « {{ $annonce->titre }} » ?">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="discret">Aucune annonce pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
