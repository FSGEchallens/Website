{{-- Champs « nouveau mot de passe » + confirmation, avec critères cochés en direct et bouton œil. --}}
@props(['nom' => 'password', 'libelle' => 'Nouveau mot de passe', 'autofocus' => false])

<div x-data="{
        mdp: '',
        confirmation: '',
        visible: false,
        get criteres() {
            return {
                longueur: this.mdp.length >= 12 && this.mdp.length <= 64,
                majuscule: /\p{Lu}/u.test(this.mdp),
                minuscule: /\p{Ll}/u.test(this.mdp),
                chiffre: /\p{N}/u.test(this.mdp),
                special: /[\p{Z}\p{S}\p{P}]/u.test(this.mdp),
                identiques: this.mdp !== '' && this.mdp === this.confirmation,
            };
        },
    }">
    <div @class(['champ', 'champ--erreur' => $errors->has($nom)])>
        <label for="champ-{{ $nom }}">{{ $libelle }}</label>
        <div class="avec-oeil">
            <input id="champ-{{ $nom }}" type="password" :type="visible ? 'text' : 'password'" wire:model="{{ $nom }}"
                   x-on:input="mdp = $event.target.value" autocomplete="new-password" maxlength="64"
                   aria-describedby="regles-{{ $nom }}" @if ($autofocus) autofocus @endif>
            <x-bouton-oeil />
        </div>
        @error($nom) <div class="erreur">{{ $message }}</div> @enderror
    </div>

    <div class="champ">
        <label for="champ-{{ $nom }}_confirmation">Confirmer le mot de passe</label>
        <div class="avec-oeil">
            <input id="champ-{{ $nom }}_confirmation" type="password" :type="visible ? 'text' : 'password'"
                   wire:model="{{ $nom }}_confirmation" x-on:input="confirmation = $event.target.value"
                   autocomplete="new-password" maxlength="64">
            <x-bouton-oeil />
        </div>
    </div>

    <ul class="criteres" id="regles-{{ $nom }}" aria-live="polite">
        <li :class="criteres.longueur && 'ok'">12 caractères au minimum (64 au maximum)</li>
        <li :class="criteres.majuscule && 'ok'">au moins une majuscule (A–Z)</li>
        <li :class="criteres.minuscule && 'ok'">au moins une minuscule (a–z)</li>
        <li :class="criteres.chiffre && 'ok'">au moins un chiffre (0–9)</li>
        <li :class="criteres.special && 'ok'">
            au moins un caractère spécial, par exemple
            <code>! ? @ # $ % &amp; * - _ + = . , ; :</code>
            <span class="discret">(tous les signes sont acceptés, y compris l'espace)</span>
        </li>
        <li :class="criteres.identiques && 'ok'">les deux mots de passe sont identiques</li>
    </ul>
</div>
