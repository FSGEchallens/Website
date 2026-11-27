<div>
    @php
        $parId = $membres->keyBy('id');
        $noms = fn (array $ids) => collect($ids)->map(fn ($id) => $parId->get($id)?->nomComplet())->filter()->implode(', ');
        $sallesParId = $salles->keyBy('id');
        $nomsSalles = fn (array $ids) => \App\Models\Salle::libellePour($ids, $salles);
    @endphp

    <div class="barre">
        <h1>Groupes</h1>
        <div class="actions">
            <button type="button" class="bouton bouton--secondaire bouton--petit" x-data
                    x-on:click="document.querySelectorAll('[data-arbre]').forEach(e => e.dispatchEvent(new CustomEvent('tout', { detail: true })))">Tout déplier</button>
            <button type="button" class="bouton bouton--secondaire bouton--petit" x-data
                    x-on:click="document.querySelectorAll('[data-arbre]').forEach(e => e.dispatchEvent(new CustomEvent('tout', { detail: false })))">Tout replier</button>
            <button type="button" class="bouton" wire:click="nouveauGroupe">+ Nouveau groupe</button>
        </div>
    </div>

    <p class="discret">
        Un <strong>groupe</strong> correspond à une page du site (description, âge, tarif). Il contient des <strong>cours</strong>
        (en général un par créneau hebdomadaire). Un cours qui a plusieurs entraînements par semaine est découpé en <strong>sessions</strong> ;
        la salle et les moniteurs d’une session sont ceux du cours s’ils ne sont pas indiqués.
    </p>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif

    <div class="arbre">
        @forelse ($groupes as $groupe)
            <div class="arbre__groupe" wire:key="groupe-{{ $groupe->id }}" data-arbre
                 x-data="{ ouvert: sessionStorage.getItem('arbre-g-{{ $groupe->id }}') === '1' }"
                 x-effect="sessionStorage.setItem('arbre-g-{{ $groupe->id }}', ouvert ? '1' : '0')"
                 x-on:tout="ouvert = $event.detail">
                <div @class(['arbre__ligne', 'arbre__ligne--groupe', 'arbre__ligne--masquee' => ! $groupe->visible])>
                    <button type="button" class="arbre__plus" x-on:click="ouvert = ! ouvert" :aria-expanded="ouvert"
                            x-text="ouvert ? '−' : '+'" aria-label="Afficher les cours de {{ $groupe->nom }}">+</button>
                    <div class="arbre__texte">
                        <strong>{{ $groupe->nom }}</strong>
                        @if ($groupe->categorie) <span class="badge">{{ $groupe->categorie }}</span> @endif
                        @unless ($groupe->visible) <span class="badge">masqué</span> @endunless
                        <span class="arbre__details">
                            {{ collect([$groupe->age, $groupe->prix !== null ? 'CHF '.rtrim(rtrim(number_format((float) $groupe->prix, 2, '.', ''), '0'), '.') : null, $groupe->cours->count().' cours'])->filter()->implode(' · ') }}
                        </span>
                    </div>
                    <div class="arbre__actions">
                        <button type="button" class="bouton-icone" wire:click="deplacer('groupe', {{ $groupe->id }}, -1)" @disabled($loop->first) title="Monter" aria-label="Monter">↑</button>
                        <button type="button" class="bouton-icone" wire:click="deplacer('groupe', {{ $groupe->id }}, 1)" @disabled($loop->last) title="Descendre" aria-label="Descendre">↓</button>
                        <button type="button" class="lien" wire:click="modifierGroupe({{ $groupe->id }})">Modifier</button>
                        <button type="button" class="lien" wire:click="nouveauCours({{ $groupe->id }})" x-on:click="ouvert = true">+ Cours</button>
                        <button type="button" class="lien lien--danger" wire:click="supprimer('groupe', {{ $groupe->id }})"
                                wire:confirm="Supprimer le groupe « {{ $groupe->nom }} » ?">Supprimer</button>
                    </div>
                </div>

                <div class="arbre__enfants" x-show="ouvert" x-cloak>
                    @forelse ($groupe->cours as $cours)
                        <div class="arbre__cours" wire:key="cours-{{ $cours->id }}" data-arbre
                             x-data="{ ouvert: sessionStorage.getItem('arbre-c-{{ $cours->id }}') === '1' }"
                             x-effect="sessionStorage.setItem('arbre-c-{{ $cours->id }}', ouvert ? '1' : '0')"
                             x-on:tout.stop="ouvert = $event.detail">
                            <div @class(['arbre__ligne', 'arbre__ligne--masquee' => ! $cours->actif])>
                                @if ($cours->sessions->isNotEmpty())
                                    <button type="button" class="arbre__plus" x-on:click="ouvert = ! ouvert" :aria-expanded="ouvert"
                                            x-text="ouvert ? '−' : '+'" aria-label="Afficher les sessions">+</button>
                                @else
                                    <span class="arbre__plus arbre__plus--vide" aria-hidden="true"></span>
                                @endif
                                <div class="arbre__texte">
                                    <strong>{{ $cours->nom ?: ($cours->libelleHoraire() ?? ($cours->sessions->isNotEmpty() ? $cours->sessions->count().' sessions par semaine' : 'Horaire à compléter')) }}</strong>
                                    @if ($cours->nom && $cours->libelleHoraire()) <span>{{ $cours->libelleHoraire() }}</span> @endif
                                    @unless ($cours->actif) <span class="badge">inactif</span> @endunless
                                    <span class="arbre__details">
                                        {{ collect([$nomsSalles($cours->idsSalles()), $noms($cours->idsMoniteurs()), $cours->remarque])->filter()->implode(' · ') }}
                                    </span>
                                </div>
                                <div class="arbre__actions">
                                    <button type="button" class="bouton-icone" wire:click="deplacer('cours', {{ $cours->id }}, -1)" @disabled($loop->first) title="Monter" aria-label="Monter">↑</button>
                                    <button type="button" class="bouton-icone" wire:click="deplacer('cours', {{ $cours->id }}, 1)" @disabled($loop->last) title="Descendre" aria-label="Descendre">↓</button>
                                    <button type="button" class="lien" wire:click="modifierCours({{ $cours->id }})">Modifier</button>
                                    <button type="button" class="lien" wire:click="nouvelleSession({{ $cours->id }})" x-on:click="ouvert = true">+ Session</button>
                                    <button type="button" class="lien lien--danger" wire:click="supprimer('cours', {{ $cours->id }})"
                                            wire:confirm="Supprimer ce cours ?">Supprimer</button>
                                </div>
                            </div>

                            @if ($cours->sessions->isNotEmpty())
                                <div class="arbre__enfants" x-show="ouvert" x-cloak>
                                    @foreach ($cours->sessions as $session)
                                        <div class="arbre__ligne arbre__ligne--session" wire:key="session-{{ $session->id }}">
                                            <span class="arbre__plus arbre__plus--vide" aria-hidden="true"></span>
                                            <div class="arbre__texte">
                                                <strong>{{ $session->libelleHoraire() }}</strong>
                                                <span class="arbre__details">
                                                    {{ collect([
                                                        $session->idsSalles() !== [] ? $nomsSalles($session->idsSalles()) : ($cours->idsSalles() !== [] ? $nomsSalles($cours->idsSalles()).' (comme le cours)' : null),
                                                        $session->idsMoniteurs() !== [] ? $noms($session->idsMoniteurs()) : ($cours->idsMoniteurs() !== [] ? $noms($cours->idsMoniteurs()).' (comme le cours)' : null),
                                                        $session->remarque,
                                                    ])->filter()->implode(' · ') }}
                                                </span>
                                            </div>
                                            <div class="arbre__actions">
                                                <button type="button" class="bouton-icone" wire:click="deplacer('session', {{ $session->id }}, -1)" @disabled($loop->first) title="Monter" aria-label="Monter">↑</button>
                                                <button type="button" class="bouton-icone" wire:click="deplacer('session', {{ $session->id }}, 1)" @disabled($loop->last) title="Descendre" aria-label="Descendre">↓</button>
                                                <button type="button" class="lien" wire:click="modifierSession({{ $session->id }})">Modifier</button>
                                                <button type="button" class="lien lien--danger" wire:click="supprimer('session', {{ $session->id }})"
                                                        wire:confirm="Supprimer cette session ?">Supprimer</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="discret arbre__vide">Aucun cours pour le moment.</p>
                    @endforelse
                </div>
            </div>
        @empty
            <p class="discret">Aucun groupe pour le moment.</p>
        @endforelse
    </div>

    @if ($type !== null)
        <div class="fenetre" role="dialog" aria-modal="true" aria-labelledby="titre-fenetre-cours" wire:key="fenetre-{{ $type }}-{{ $editionId }}"
             x-data x-on:keydown.escape.window="$wire.fermer()">
            <div @class(['fenetre__boite', 'fenetre__boite--large' => $type === 'groupe'])>
                <h3 id="titre-fenetre-cours">
                    @if ($type === 'groupe')
                        {{ $editionId ? 'Modifier le groupe' : 'Nouveau groupe' }}
                    @elseif ($type === 'cours')
                        {{ $editionId ? 'Modifier le cours' : 'Nouveau cours' }} @if ($parent) <small>— {{ $parent->nom }}</small> @endif
                    @else
                        {{ $editionId ? 'Modifier la session' : 'Nouvelle session' }} @if ($parent) <small>— {{ $parent->libelle() }}</small> @endif
                    @endif
                </h3>

                <form wire:submit="enregistrer" novalidate>
                    @if ($type === 'groupe')
                        <div class="grille">
                            <x-champ nom="donnees.nom" libelle="Nom" requis placeholder="Ex. Agrès kids plaisir" />
                            <div class="champ">
                                <label for="champ-categorie">Catégorie (pour le menu)</label>
                                <input id="champ-categorie" type="text" wire:model="donnees.categorie" list="liste-categories" placeholder="Ex. Agrès">
                                <datalist id="liste-categories">
                                    @foreach ($categories as $categorie) <option value="{{ $categorie }}"></option> @endforeach
                                </datalist>
                            </div>
                        </div>

                        <div class="bloc">
                            <label class="libelle-bloc">Description</label>
                            <x-editeur-texte nom="donnees.description" :contenu="$donnees['description'] ?? ''" />
                        </div>

                        <div class="grille">
                            <x-champ nom="donnees.age" libelle="Âge" placeholder="Ex. De 7 à 14 ans." />
                            <x-champ nom="donnees.prerequis" libelle="Prérequis" placeholder="Ex. Aucun prérequis !" />
                        </div>

                        <div @class(['champ', 'champ--erreur' => $errors->has('donnees.concours')])>
                            <label for="champ-concours">Concours et manifestations</label>
                            <textarea id="champ-concours" rows="2" wire:model="donnees.concours"></textarea>
                            @error('donnees.concours') <div class="erreur">{{ $message }}</div> @enderror
                        </div>

                        <div class="grille">
                            <x-champ nom="donnees.prix" libelle="Prix par année scolaire (CHF)" type="number" min="0" step="0.05" />
                            <div @class(['champ', 'champ--erreur' => $errors->has('donnees.infos_tarif')])>
                                <label for="champ-infos-tarif">Informations du tarif</label>
                                <textarea id="champ-infos-tarif" rows="3" wire:model="donnees.infos_tarif" placeholder="Une information par ligne"></textarea>
                                <div class="aide">Une information par ligne (cours d’essai, durée, assurance…).</div>
                                @error('donnees.infos_tarif') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <label class="case"><input type="checkbox" wire:model="donnees.visible"> Afficher ce groupe sur le site</label>
                    @else
                        @if ($type === 'cours')
                            <x-champ nom="donnees.nom" libelle="Nom du cours (facultatif)" aide="Sinon, c’est l’horaire qui est affiché." />
                        @endif

                        <div class="grille grille--trois">
                            <div @class(['champ', 'champ--erreur' => $errors->has('donnees.jour')])>
                                <label for="champ-jour">Jour @if ($type === 'session')<span aria-hidden="true">*</span>@endif</label>
                                <select id="champ-jour" wire:model="donnees.jour">
                                    <option value="">—</option>
                                    @foreach (\App\Models\Cours::JOURS as $numero => $jour)
                                        <option value="{{ $numero }}">{{ $jour }}</option>
                                    @endforeach
                                </select>
                                @error('donnees.jour') <div class="erreur">{{ $message }}</div> @enderror
                            </div>
                            <x-champ nom="donnees.heure_debut" libelle="Début" type="time" :requis="$type === 'session'" />
                            <x-champ nom="donnees.heure_fin" libelle="Fin" type="time" :requis="$type === 'session'" />
                        </div>
                        @if ($type === 'cours')
                            <div class="aide">Laissez le jour et les heures vides si le cours a plusieurs sessions par semaine.</div>
                        @endif

                        <fieldset class="groupe">
                            <legend>Salles</legend>
                            @if (($donnees['salles'] ?? []) === [])
                                <p class="discret">
                                    @if ($type === 'session' && $parent && $parent->idsSalles() !== [])
                                        Aucune : ce sont celles du cours ({{ $nomsSalles($parent->idsSalles()) }}).
                                    @else
                                        Aucune salle choisie.
                                    @endif
                                </p>
                            @else
                                <ul class="liste-moniteurs">
                                    @foreach ($donnees['salles'] as $index => $id)
                                        <li wire:key="salle-choisie-{{ $id }}">
                                            {{ $sallesParId->get($id)?->libelleComplet() ?? 'Salle introuvable' }}
                                            <button type="button" class="lien lien--danger" wire:click="retirerSalle({{ $index }})">retirer</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="ajout-moniteur">
                                <select wire:model="salleAAjouter" aria-label="Salle à ajouter">
                                    @php
                                        $choisies = $donnees['salles'] ?? [];
                                        // Une fois une salle choisie, seules les autres salles du même bâtiment sont proposées.
                                        $batimentChoisi = $choisies !== [] ? $sallesParId->get($choisies[0])?->batiment_id : null;
                                        $proposees = $salles->reject(fn ($s) => in_array($s->id, $choisies, true) || ($batimentChoisi !== null && $s->batiment_id !== $batimentChoisi));
                                    @endphp
                                    <option value="">{{ $batimentChoisi ? '— Ajouter une autre salle du bâtiment —' : '— Ajouter une salle —' }}</option>
                                    @foreach ($proposees->groupBy(fn ($s) => $s->batiment?->nom ?? 'Sans bâtiment') as $nomBatiment => $sallesDuBatiment)
                                        <optgroup label="{{ $nomBatiment }}">
                                            @foreach ($sallesDuBatiment as $salle)
                                                <option value="{{ $salle->id }}">{{ $salle->nom }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <button type="button" class="bouton bouton--secondaire bouton--petit" wire:click="ajouterSalle">Ajouter</button>
                            </div>
                            <div class="aide">
                                Plusieurs salles possibles si elles sont dans le même bâtiment (ex. Court-Champ : Salle 3 + Salle 4 pour la salle double entière).
                                Une salle manque ? Ajoutez-la dans <a href="{{ route('salles.gestion') }}" target="_blank">Bâtiments et salles</a>, puis rouvrez cette fenêtre.
                            </div>
                            @error('donnees.salles') <div class="erreur">{{ $message }}</div> @enderror
                            @error('donnees.salles.*') <div class="erreur">{{ $message }}</div> @enderror
                        </fieldset>

                        <fieldset class="groupe">
                            <legend>Moniteurs</legend>
                            @if (($donnees['moniteurs'] ?? []) === [])
                                <p class="discret">
                                    @if ($type === 'session' && $parent && $parent->idsMoniteurs() !== [])
                                        Aucun : ce sont ceux du cours ({{ $noms($parent->idsMoniteurs()) }}).
                                    @else
                                        Aucun moniteur choisi.
                                    @endif
                                </p>
                            @else
                                <ul class="liste-moniteurs">
                                    @foreach ($donnees['moniteurs'] as $index => $id)
                                        <li wire:key="moniteur-{{ $id }}">
                                            {{ $parId->get($id)?->nomComplet() ?? $moniteursPossibles->firstWhere('id', $id)?->nomComplet() ?? 'Membre introuvable' }}
                                            @if ($index === 0)
                                                <span class="badge badge--comite-page">principal</span>
                                            @else
                                                <span class="badge">secondaire</span>
                                                <button type="button" class="lien" wire:click="rendrePrincipal({{ $index }})">rendre principal</button>
                                            @endif
                                            <button type="button" class="lien lien--danger" wire:click="retirerMoniteur({{ $index }})">retirer</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="ajout-moniteur">
                                <select wire:model="moniteurAAjouter" aria-label="Moniteur à ajouter">
                                    <option value="">— Ajouter un moniteur —</option>
                                    @foreach ($moniteursPossibles as $possible)
                                        @unless (in_array($possible->id, $donnees['moniteurs'] ?? [], true))
                                            <option value="{{ $possible->id }}">{{ $possible->nom }} {{ $possible->prenom }}</option>
                                        @endunless
                                    @endforeach
                                </select>
                                <button type="button" class="bouton bouton--secondaire bouton--petit" wire:click="ajouterMoniteur">Ajouter</button>
                            </div>
                            <div class="aide">Tous les membres de la société sont proposés. Le premier est le moniteur principal.</div>
                            @error('donnees.moniteurs.*') <div class="erreur">{{ $message }}</div> @enderror
                        </fieldset>

                        <x-champ nom="donnees.remarque" libelle="Remarque affichée" placeholder="Ex. COMPLET pour 2026-2027" />

                        @if ($type === 'cours')
                            <label class="case"><input type="checkbox" wire:model="donnees.actif"> Cours actif</label>
                        @endif
                    @endif

                    <div class="actions actions--fenetre">
                        <button type="button" class="bouton bouton--secondaire" wire:click="fermer">Annuler</button>
                        <button type="submit" class="bouton" wire:loading.attr="disabled">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
