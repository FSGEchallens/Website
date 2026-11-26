{{-- Numéro de mobile avec liste des pays (drapeau + indicatif) : Suisse en premier et présélectionnée,
     puis les autres pays par ordre alphabétique. Propriétés Livewire : « mobile_pays » et « mobile ». --}}
@props(['requis' => false])

<div @class(['champ', 'champ--erreur' => $errors->has('mobile') || $errors->has('mobile_pays')]) x-data>
    <label for="champ-mobile">Mobile @if ($requis)<span aria-hidden="true">*</span>@endif</label>
    <div class="groupe-mobile">
        <select x-ref="paysMobile" wire:model="mobile_pays" class="select-drapeaux" aria-label="Pays (indicatif téléphonique)">
            @foreach (\App\Support\Indicatifs::liste() as $iso => $pays)
                <option value="{{ $iso }}">{{ $pays['drapeau'] }} {{ $pays['nom'] }} (+{{ $pays['indicatif'] }})</option>
            @endforeach
        </select>
        <input id="champ-mobile" type="tel" wire:model="mobile" autocomplete="tel-national" inputmode="tel"
               placeholder="79 123 45 67" @if ($requis) required @endif
               x-on:blur="formaterChampMobile($el, $refs.paysMobile.value)">
    </div>
    @error('mobile_pays') <div class="erreur">{{ $message }}</div> @enderror
    @error('mobile') <div class="erreur">{{ $message }}</div> @enderror
</div>
