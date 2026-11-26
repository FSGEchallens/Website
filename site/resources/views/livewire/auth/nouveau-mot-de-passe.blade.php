<div class="carte carte--etroite">
    @if ($invitation)
        <h1>Bienvenue !</h1>
        <p>Choisissez le mot de passe de votre compte. Vous l'utiliserez avec votre e-mail société pour vous connecter.</p>
    @else
        <h1>Nouveau mot de passe</h1>
    @endif

    <form wire:submit="enregistrer" novalidate
          x-data="{
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
        <div class="champ">
            <label for="champ-email">E-mail société</label>
            <input id="champ-email" type="email" value="{{ $email }}" autocomplete="username" readonly class="lecture-seule">
        </div>

        <div @class(['champ', 'champ--erreur' => $errors->has('password')])>
            <label for="champ-password">Nouveau mot de passe</label>
            <div class="avec-oeil">
                <input id="champ-password" type="password" :type="visible ? 'text' : 'password'" wire:model="password"
                       x-on:input="mdp = $event.target.value" autocomplete="new-password" maxlength="64"
                       aria-describedby="regles-mot-de-passe" autofocus>
                <x-bouton-oeil />
            </div>
            @error('password') <div class="erreur">{{ $message }}</div> @enderror
        </div>

        <ul class="criteres" id="regles-mot-de-passe" aria-live="polite">
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

        <div class="champ">
            <label for="champ-password_confirmation">Confirmer le mot de passe</label>
            <div class="avec-oeil">
                <input id="champ-password_confirmation" type="password" :type="visible ? 'text' : 'password'" wire:model="password_confirmation"
                       x-on:input="confirmation = $event.target.value" autocomplete="new-password" maxlength="64">
                <x-bouton-oeil />
            </div>
        </div>

        <p class="discret">
            Conseil : une courte phrase facile à retenir fait un excellent mot de passe,
            par exemple <em>« Le saut en longueur, c'est 4 fois mieux ! »</em> (mais n'utilisez pas celle-ci).
            Les mots de passe connus pour avoir fuité sur internet sont refusés.
        </p>

        <div class="actions">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="enregistrer">Enregistrer le mot de passe</span>
                <span wire:loading wire:target="enregistrer">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
