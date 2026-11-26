{{-- Photo d'un membre avec import, aperçu et retrait (composant Livewire utilisant GerePhotoMembre). --}}
@props(['url', 'aUnePhoto' => false, 'modifiable' => false, 'alt' => 'Photo'])

<div class="photo-membre">
    <img src="{{ $url }}" alt="{{ $alt }}" width="160" height="160">
    <div wire:loading wire:target="photo" class="discret">Chargement de la photo…</div>
    @if ($modifiable)
        <label class="bouton bouton--secondaire bouton--petit">
            {{ $aUnePhoto ? 'Changer la photo' : 'Importer une photo' }}
            <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="visuellement-cache">
        </label>
        @if ($aUnePhoto)
            <button type="button" class="lien" wire:click="retirerPhoto">Retirer la photo</button>
        @endif
        <div class="aide">JPEG, PNG ou WebP, 10 Mo au maximum. La photo est recadrée en carré.</div>
    @endif
    @error('photo') <div class="erreur">{{ $message }}</div> @enderror
</div>
