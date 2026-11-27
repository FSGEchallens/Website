<div class="statuts-gestion">
    <form wire:submit="enregistrer" novalidate x-data="controleFichiers(10, ['pdf'])" x-on:change.capture="verifier($event)">
        <label class="bouton bouton--secondaire bouton--petit">
            Choisir un nouveau PDF
            <input type="file" wire:model="fichier" accept="application/pdf,.pdf" class="visuellement-cache">
        </label>

        @if ($fichier)
            <span class="discret">{{ $fichier->getClientOriginalName() }}</span>
            <button type="submit" class="bouton bouton--petit" wire:loading.attr="disabled">Remplacer les statuts</button>
        @endif

        <span wire:loading wire:target="fichier" class="discret">Envoi en cours…</span>

        <div class="aide">PDF uniquement, 10 Mo au maximum. L’ancienne version reste restaurable depuis le journal.</div>
        <div class="erreur" x-show="erreurFichier" x-text="erreurFichier" x-cloak role="alert"></div>
        @error('fichier') <div class="erreur">{{ $message }}</div> @enderror
    </form>
</div>
