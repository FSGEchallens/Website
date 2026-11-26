<div>
    <div class="barre">
        <h1>{{ $album ? 'Album « '.$album->titre.' »' : 'Nouvel album' }}</h1>
        <div class="actions">
            @if ($album)
                <a href="{{ route('galerie.album', $album) }}" class="bouton bouton--secondaire">Voir l'album</a>
            @endif
            <a href="{{ route('galerie.index') }}" class="bouton bouton--secondaire">Retour à la galerie</a>
        </div>
    </div>

    @if ($confirmation)
        <div class="alerte alerte--succes" role="status">{{ $confirmation }}</div>
    @endif

    <section class="carte">
        <h2>Informations de l'album</h2>
        <form wire:submit="enregistrerAlbum" novalidate>
            <div class="grille">
                <x-champ nom="titre" libelle="Titre" requis placeholder="Ex. Spectacle 2026" />
                <x-champ nom="date" libelle="Date" type="date" />
            </div>
            <div @class(['champ', 'champ--erreur' => $errors->has('description')])>
                <label for="champ-description">Description (facultative)</label>
                <textarea id="champ-description" wire:model="description" rows="2" maxlength="2000"></textarea>
                @error('description') <div class="erreur">{{ $message }}</div> @enderror
            </div>
            <label class="case"><input type="checkbox" wire:model="visible"> Album publié (décocher pour le préparer sans l'afficher)</label>
            <div class="actions bloc">
                <button type="submit" class="bouton">{{ $album ? 'Enregistrer' : 'Créer l\'album' }}</button>
            </div>
        </form>
    </section>

    @if ($album)
        <section class="carte">
            <div class="barre">
                <h2>Photos ({{ $photos->count() }} / {{ \App\Models\Album::PHOTOS_MAX }})</h2>
            </div>

            {{-- Envoi des photos : réduites par le navigateur puis envoyées une par une. --}}
            <div class="envoi-galerie" x-data="envoiGalerie(25)">
                <label class="bouton" :class="{ 'bouton--desactive': enCours }">
                    + Ajouter des photos
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="visuellement-cache"
                           x-on:change="envoyer($event)" :disabled="enCours">
                </label>
                <span class="discret">Vous pouvez en sélectionner plusieurs à la fois (JPEG, PNG ou WebP).</span>

                <div x-show="enCours || termine" x-cloak class="envoi-galerie__progression" role="status">
                    <progress max="100" :value="total ? Math.round(faits / total * 100) : 0"></progress>
                    <span x-text="enCours ? `${faits} / ${total} photo(s) envoyée(s)…` : `${total - erreurs.length} photo(s) ajoutée(s).`"></span>
                </div>
                <template x-for="erreur in erreurs" :key="erreur">
                    <div class="erreur" x-text="erreur" role="alert"></div>
                </template>
            </div>

            @foreach ($erreursEnvoi as $erreur)
                <div class="erreur" role="alert">{{ $erreur }}</div>
            @endforeach
            @if ($erreursEnvoi)
                <button type="button" class="lien" wire:click="effacerErreurs">Effacer ces messages</button>
            @endif

            <div class="grille-photos grille-photos--gestion">
                @forelse ($photos as $photo)
                    <figure wire:key="photo-{{ $photo->id }}" @class(['est-couverture' => $album->couverture_id === $photo->id])>
                        <img src="{{ $photo->url(miniature: true) }}" alt="" loading="lazy">
                        <figcaption>
                            @if ($album->couverture_id === $photo->id)
                                <span class="badge">★ Couverture</span>
                            @else
                                <button type="button" class="lien" wire:click="choisirCouverture({{ $photo->id }})" title="Utiliser comme photo de couverture">☆ Couverture</button>
                            @endif
                            <button type="button" class="lien lien--danger" wire:click="supprimerPhoto({{ $photo->id }})"
                                    wire:confirm="Supprimer cette photo ?" title="Supprimer">✕</button>
                        </figcaption>
                    </figure>
                @empty
                    <p class="discret">Pas encore de photo dans cet album.</p>
                @endforelse
            </div>
        </section>

        <section class="carte zone-danger">
            <h2>Supprimer l'album</h2>
            <p class="discret">L'album et ses {{ $photos->count() }} photo(s) partent à la corbeille. L'administrateur peut les restaurer depuis le journal pendant {{ config('journal.conservation_jours') }} jours.</p>
            <button type="button" class="bouton bouton--danger" wire:click="supprimerAlbum"
                    wire:confirm="Supprimer l'album « {{ $album->titre }} » et toutes ses photos ?">Supprimer l'album</button>
        </section>
    @endif
</div>
