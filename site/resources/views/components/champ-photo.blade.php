{{-- Photo d'un membre (avatar rond) avec import, recadrage (déplacer / zoomer) et retrait.
     Utilisé dans un composant Livewire qui a la propriété « photo » (trait GerePhotoMembre). --}}
@props(['url', 'aUnePhoto' => false, 'modifiable' => false, 'alt' => 'Photo'])

<div class="photo-membre" x-data="recadrageAvatar(20)">
    <img src="{{ $url }}" alt="{{ $alt }}" width="160" height="160" class="avatar-rond">
    <div wire:loading wire:target="photo" class="discret">Chargement de la photo…</div>

    @if ($modifiable)
        <label class="bouton bouton--secondaire bouton--petit">
            {{ $aUnePhoto ? 'Changer la photo' : 'Importer une photo' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="visuellement-cache" x-on:change="choisir($event)">
        </label>
        @if ($aUnePhoto)
            <button type="button" class="lien" wire:click="retirerPhoto"
                    x-on:click="typeof marquerModifie === 'function' && marquerModifie()">Retirer la photo</button>
        @endif
        <div class="aide">JPEG, PNG ou WebP. Vous pourrez la cadrer dans le rond.</div>
        <div class="erreur" x-show="erreur" x-text="erreur" x-cloak role="alert"></div>

        {{-- Fenêtre de recadrage --}}
        <div class="recadrage" x-show="ouvert" x-cloak x-on:keydown.escape.window="ouvert && fermer()"
             role="dialog" aria-modal="true" aria-label="Cadrer la photo">
            <div class="recadrage__boite">
                <h3>Cadrer la photo</h3>
                <p class="discret">Faites glisser la photo et zoomez pour bien placer le visage dans le rond.</p>

                <div class="recadrage__zone" :style="`width: ${taille}px; height: ${taille}px`"
                     x-on:pointerdown.prevent="commencerGlisse($event)" x-on:pointermove="glisser($event)"
                     x-on:pointerup="finirGlisse()" x-on:pointercancel="finirGlisse()"
                     x-on:wheel.prevent="zoomerMolette($event)">
                    <canvas x-ref="canvas" :width="taille" :height="taille"></canvas>
                    <div class="recadrage__masque" aria-hidden="true"></div>
                </div>

                <label class="recadrage__zoom">
                    <span>Zoom</span>
                    <input type="range" min="1" max="4" step="0.01" x-model.number="zoom" x-on:input="dessiner()">
                </label>

                <div x-show="envoi" class="discret">Envoi de la photo… <progress max="100" :value="progression"></progress></div>

                <div class="actions">
                    <button type="button" class="bouton" x-on:click="valider()" :disabled="envoi">Valider</button>
                    <button type="button" class="bouton bouton--secondaire" x-on:click="fermer()" :disabled="envoi">Annuler</button>
                </div>
            </div>
        </div>
    @endif

    @error('photo') <div class="erreur" role="alert">{{ $message }}</div> @enderror
</div>
