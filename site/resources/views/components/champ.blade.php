@props(['nom', 'libelle', 'type' => 'text', 'aide' => null, 'requis' => false])

<div @class(['champ', 'champ--erreur' => $errors->has($nom)])>
    <label for="champ-{{ $nom }}">{{ $libelle }}@if ($requis) <span aria-hidden="true">*</span>@endif</label>
    @if ($type === 'password')
        <div class="avec-oeil" x-data="{ visible: false }">
            <input id="champ-{{ $nom }}" :type="visible ? 'text' : 'password'" type="password" wire:model="{{ $nom }}"
                   @if ($requis) required @endif
                   @if ($errors->has($nom)) aria-invalid="true" aria-describedby="erreur-{{ $nom }}" @endif
                   {{ $attributes }}>
            <x-bouton-oeil />
        </div>
    @else
        <input id="champ-{{ $nom }}" type="{{ $type }}" wire:model="{{ $nom }}"
               @if ($requis) required @endif
               @if ($errors->has($nom)) aria-invalid="true" aria-describedby="erreur-{{ $nom }}" @endif
               {{ $attributes }}>
    @endif
    @if ($aide)
        <div class="aide">{{ $aide }}</div>
    @endif
    @error($nom)
        <div class="erreur" id="erreur-{{ $nom }}">{{ $message }}</div>
    @enderror
</div>
