{{-- Numéro de mobile avec liste des pays (drapeau + indicatif) : Suisse en premier et présélectionnée,
     puis les autres pays par ordre alphabétique. Propriétés Livewire : « mobile_pays » et « mobile ».
     Liste fermée : seul l'indicatif est affiché (ex. « +41 ») ; liste ouverte : drapeaux et noms complets. --}}
@props(['requis' => false, 'pays' => 'CH'])

@php
    $listePays = \App\Support\Indicatifs::liste();
    $choisi = $listePays[$pays] ?? $listePays['CH'];
@endphp

<div @class(['champ', 'champ--erreur' => $errors->has('mobile') || $errors->has('mobile_pays')])
     x-data="{
        affichage: @js('+'.$choisi['indicatif']),
        actualiser() {
            const option = $refs.paysMobile.selectedOptions[0];
            if (option) { this.affichage = '+' + option.dataset.indicatif; }
        },
     }"
     x-init="$nextTick(() => actualiser())">
    <label for="champ-mobile">Mobile @if ($requis)<span aria-hidden="true">*</span>@endif</label>
    <div class="groupe-mobile">
        <div class="indicatif">
            <span class="indicatif__affichage" x-text="affichage" aria-hidden="true">+{{ $choisi['indicatif'] }}</span>
            <select x-ref="paysMobile" wire:model="mobile_pays" x-on:change="actualiser()" class="select-drapeaux"
                    aria-label="Pays (indicatif téléphonique)">
                @foreach ($listePays as $iso => $infos)
                    <option value="{{ $iso }}" data-drapeau="{{ $infos['drapeau'] }}" data-indicatif="{{ $infos['indicatif'] }}" @selected($iso === $pays)>
                        {{ $infos['drapeau'] }} {{ $infos['nom'] }} (+{{ $infos['indicatif'] }})
                    </option>
                @endforeach
            </select>
        </div>
        <input id="champ-mobile" type="tel" wire:model="mobile" autocomplete="tel-national" inputmode="tel"
               placeholder="79 123 45 67" @if ($requis) required @endif
               x-on:blur="formaterChampMobile($el, $refs.paysMobile.value)">
    </div>
    @error('mobile_pays') <div class="erreur">{{ $message }}</div> @enderror
    @error('mobile') <div class="erreur">{{ $message }}</div> @enderror
</div>
