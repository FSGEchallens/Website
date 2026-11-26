<div>
    <div class="barre">
        <h1>Journal des modifications</h1>
        <span class="discret">Conservé {{ $conservation }} jours</span>
    </div>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif
    @if ($erreur)
        <div class="alerte alerte--erreur" role="alert">{{ $erreur }}</div>
    @endif

    <div class="barre">
        <div class="barre__filtres">
            <input type="search" class="recherche" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher (nom du membre, du partenaire…)" aria-label="Rechercher">
            <select class="recherche" style="max-width: 12rem" wire:model.live="type" aria-label="Type">
                <option value="">Tous les types</option>
                @foreach ($types as $classe => $libelle)
                    <option value="{{ $classe }}">{{ $libelle }}</option>
                @endforeach
            </select>
            <select class="recherche" style="max-width: 12rem" wire:model.live="action" aria-label="Action">
                <option value="">Toutes les actions</option>
                <option value="creation">Créations</option>
                <option value="modification">Modifications</option>
                <option value="suppression">Suppressions</option>
            </select>
        </div>
        <span class="discret">{{ $entrees->total() }} entrée(s)</span>
    </div>

    <div class="tableau-conteneur">
        <table class="journal">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Date</th>
                    <th>Qui</th>
                    <th>Action</th>
                    <th>Élément</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entrees as $entree)
                    <tr wire:key="entree-{{ $entree->id }}">
                        <td data-label="N°">{{ $entree->id }}</td>
                        <td data-label="Date">{{ $entree->created_at->format('d.m.Y H:i') }}</td>
                        <td data-label="Qui">{{ $entree->auteur?->nomComplet() ?? 'Système' }}</td>
                        <td data-label="Action"><span class="badge badge--action-{{ $entree->action }}">{{ $entree->libelleAction() }}</span></td>
                        <td data-label="Élément">
                            {{ $entree->libelle }}
                            @if ($entree->note) <br><span class="discret">{{ $entree->note }}</span> @endif
                        </td>
                        <td>
                            <button type="button" class="lien" wire:click="basculer({{ $entree->id }})">
                                {{ $ouverte === $entree->id ? 'Masquer' : 'Détails' }}
                            </button>
                        </td>
                    </tr>

                    @if ($ouverte === $entree->id)
                        @php
                            $libelles = \App\Livewire\Administration\JournalModifications::libellesChamps($entree->sujet_type);
                            $champs = array_unique(array_merge(array_keys($entree->anciennes_valeurs ?? []), array_keys($entree->nouvelles_valeurs ?? [])));
                        @endphp
                        <tr wire:key="details-{{ $entree->id }}" class="journal__details">
                            <td colspan="6">
                                <p class="discret">
                                    {{ $entree->created_at->format('d.m.Y à H:i:s') }}
                                    @if ($entree->ip) · adresse IP {{ $entree->ip }} @endif
                                </p>

                                <table class="journal__valeurs">
                                    <thead>
                                        <tr><th>Champ</th><th>Avant</th><th>Après</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($champs as $champ)
                                            @php
                                                $avant = \App\Livewire\Administration\JournalModifications::formaterValeur($champ, $entree->anciennes_valeurs[$champ] ?? null);
                                                $apres = \App\Livewire\Administration\JournalModifications::formaterValeur($champ, $entree->nouvelles_valeurs[$champ] ?? null);
                                            @endphp
                                            <tr @class(['journal__change' => $entree->action === 'modification'])>
                                                <td>{{ $libelles[$champ] ?? $champ }}</td>
                                                <td>{{ $entree->action === 'creation' ? '' : $avant }}</td>
                                                <td>{{ $entree->action === 'suppression' ? '' : $apres }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                @if ($entree->fichiers)
                                    <p><strong>Fichier(s) archivé(s)</strong> :</p>
                                    <div class="journal__fichiers">
                                        @foreach ($entree->fichiers as $index => $fichier)
                                            <a href="{{ route('journal.fichier', [$entree, $index]) }}" target="_blank" rel="noopener">
                                                <img src="{{ route('journal.fichier', [$entree, $index]) }}" alt="{{ basename($fichier['chemin']) }}">
                                                <span class="discret">{{ basename($fichier['chemin']) }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($entree->estRestaurable())
                                    <button type="button" class="bouton bouton--petit" wire:click="restaurer({{ $entree->id }})"
                                            wire:confirm="{{ $entree->action === 'suppression'
                                                ? 'Faire revenir cet élément supprimé ('.$entree->libelle.') ?'
                                                : 'Remettre les valeurs « Avant » pour '.$entree->libelle.' ? Les modifications faites depuis sur ces champs seront remplacées.' }}">
                                        {{ $entree->action === 'suppression' ? 'Restaurer l\'élément supprimé' : 'Restaurer les valeurs « Avant »' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="discret">Aucune entrée dans le journal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entrees->links('pagination.simple') }}
</div>
