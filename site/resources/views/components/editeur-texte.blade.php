{{-- Petit éditeur de texte (gras, italique, souligné, barré, couleur, listes, lien). Sans bibliothèque externe.
     Le texte est nettoyé côté serveur (App\Support\NettoyeurHtml) avant d'être enregistré. --}}
@props(['nom', 'contenu' => ''])

<div @class(['champ', 'champ--erreur' => $errors->has($nom)])>
    <div class="editeur" x-data="editeurTexte(@js($nom))">
        <div class="editeur__barre" role="toolbar" aria-label="Mise en forme du texte">
            <button type="button" x-on:mousedown.prevent x-on:click="commande('bold')" :class="{ actif: etats.bold }" title="Gras (Ctrl+B)" aria-label="Gras"><strong>G</strong></button>
            <button type="button" x-on:mousedown.prevent x-on:click="commande('italic')" :class="{ actif: etats.italic }" title="Italique (Ctrl+I)" aria-label="Italique"><em>I</em></button>
            <button type="button" x-on:mousedown.prevent x-on:click="commande('underline')" :class="{ actif: etats.underline }" title="Souligné (Ctrl+U)" aria-label="Souligné"><u>S</u></button>
            <button type="button" x-on:mousedown.prevent x-on:click="commande('strikeThrough')" :class="{ actif: etats.strikeThrough }" title="Barré" aria-label="Barré"><s>B</s></button>

            <span class="editeur__separateur" aria-hidden="true"></span>

            <div class="editeur__couleurs">
                <button type="button" x-on:mousedown.prevent x-on:click="paletteOuverte = ! paletteOuverte" title="Couleur du texte" aria-label="Couleur du texte" :aria-expanded="paletteOuverte.toString()">
                    <span class="editeur__icone-couleur">A</span> ▾
                </button>
                <div class="editeur__palette" x-show="paletteOuverte" x-cloak x-on:click.outside="paletteOuverte = false">
                    <template x-for="couleur in couleurs" :key="couleur.code">
                        <button type="button" x-on:mousedown.prevent x-on:click="appliquerCouleur(couleur.code)"
                                :title="couleur.nom" :aria-label="couleur.nom" :style="`background: ${couleur.code}`"></button>
                    </template>
                </div>
            </div>

            <span class="editeur__separateur" aria-hidden="true"></span>

            <button type="button" x-on:mousedown.prevent x-on:click="commande('insertUnorderedList')" :class="{ actif: etats.insertUnorderedList }" title="Liste à puces" aria-label="Liste à puces">• ≡</button>
            <button type="button" x-on:mousedown.prevent x-on:click="commande('insertOrderedList')" :class="{ actif: etats.insertOrderedList }" title="Liste numérotée" aria-label="Liste numérotée">1. ≡</button>
            <button type="button" x-on:mousedown.prevent x-on:click="ajouterLien()" title="Ajouter un lien" aria-label="Ajouter un lien">🔗</button>
            <button type="button" x-on:mousedown.prevent x-on:click="commande('unlink')" title="Retirer le lien" aria-label="Retirer le lien">⛓̸</button>

            <span class="editeur__separateur" aria-hidden="true"></span>

            <button type="button" x-on:mousedown.prevent x-on:click="commande('removeFormat')" title="Effacer la mise en forme" aria-label="Effacer la mise en forme">✕ Tx</button>
        </div>

        <div class="editeur__zone texte-riche" contenteditable="true" x-ref="zone" wire:ignore
             role="textbox" aria-multiline="true" aria-label="Texte"
             x-on:input="synchroniser()" x-on:paste="coller($event)" x-on:keyup="majEtats()" x-on:mouseup="majEtats()">{!! \App\Support\NettoyeurHtml::nettoyer($contenu) !!}</div>
    </div>
    @error($nom) <div class="erreur">{{ $message }}</div> @enderror
    <div class="aide">Astuce : un texte collé depuis Word ou un site arrive sans mise en forme ; utilisez ensuite les boutons.</div>
</div>
