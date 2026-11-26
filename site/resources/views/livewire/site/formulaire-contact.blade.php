<section class="carte contact__formulaire" aria-labelledby="titre-formulaire">
    <h2 id="titre-formulaire">Formulaire de contact</h2>

    @if ($envoye)
        <div class="alerte alerte--succes" role="status">
            Merci ! Votre message a bien été envoyé. Nous vous répondrons dès que possible.
        </div>
        <button type="button" class="lien" wire:click="nouveauMessage">Envoyer un autre message</button>
    @else
        <form wire:submit="envoyer" novalidate>
            <div class="grille">
                <x-champ nom="prenom" libelle="Prénom" requis autocomplete="given-name" />
                <x-champ nom="nom" libelle="Nom" requis autocomplete="family-name" />
            </div>
            <x-champ nom="email" libelle="Adresse e-mail" type="email" requis autocomplete="email" />
            <x-champ nom="sujet" libelle="Sujet" />

            <div @class(['champ', 'champ--erreur' => $errors->has('message')])>
                <label for="champ-message">Votre message <span aria-hidden="true">*</span></label>
                <textarea id="champ-message" wire:model="message" rows="7" maxlength="5000" required></textarea>
                @error('message') <div class="erreur">{{ $message }}</div> @enderror
            </div>

            {{-- Champ piège : caché aux humains (et aux lecteurs d'écran), les robots le remplissent. --}}
            <div class="champ-piege" aria-hidden="true">
                <label for="champ-site_web">Ne pas remplir ce champ</label>
                <input id="champ-site_web" type="text" wire:model="site_web" tabindex="-1" autocomplete="off">
            </div>

            <div @class(['champ', 'champ-question', 'champ--erreur' => $errors->has('reponse')])>
                <label for="champ-reponse">Question anti-robot : {{ $question }} <span aria-hidden="true">*</span></label>
                <input id="champ-reponse" type="text" wire:model="reponse" inputmode="numeric" autocomplete="off" maxlength="20"
                       placeholder="Votre réponse (en chiffres ou en lettres)">
                @error('reponse') <div class="erreur">{{ $message }}</div> @enderror
            </div>

            <p class="discret">Vos coordonnées servent uniquement à vous répondre ; elles ne sont pas conservées sur le site.</p>

            <div class="actions">
                <button type="submit" class="bouton">
                    <span wire:loading.remove wire:target="envoyer">Envoyer</span>
                    <span wire:loading wire:target="envoyer">Envoi…</span>
                </button>
            </div>
        </form>
    @endif
</section>
