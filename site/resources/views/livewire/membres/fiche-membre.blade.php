<div x-data="protectionModifications" x-on:input="marquerModifie()" x-on:change="marquerModifie()" x-on:submit="avantEnregistrement()">
    <div class="barre">
        <h1>{{ $membre ? $membre->nomComplet() : 'Nouveau membre' }}</h1>
        @if ($membre && ! $enEdition)
            <button type="button" class="bouton" wire:click="modifier">Modifier</button>
        @endif
    </div>

    @if ($membre && ! $enEdition)
        <p class="alerte alerte--info">Fiche verrouillée pour éviter une modification involontaire. Cliquez sur <strong>Modifier</strong> pour la changer.</p>
    @endif

    <form wire:submit="enregistrer" novalidate>
        <fieldset class="verrouillable" @disabled(! $enEdition)>
            <legend class="visuellement-cache">Fiche membre</legend>

            <section class="carte">
                <h2>Identité</h2>
                <div class="identite">
                    <x-champ-photo :url="$urlApercu" :a-une-photo="$aUnePhoto" :modifiable="$enEdition"
                                   :alt="'Photo de '.(trim($prenom.' '.$nom) ?: 'ce membre')" />

                    <div class="grille">
                        <div @class(['champ', 'champ--erreur' => $errors->has('civilite')])>
                            <label for="champ-civilite">Titre <span aria-hidden="true">*</span></label>
                            <select id="champ-civilite" wire:model.live="civilite" required>
                                @if ($civilite === '')
                                    <option value="" disabled selected>— choisir —</option>
                                @endif
                                @foreach ($civilites as $option)
                                    <option value="{{ $option->value }}" @selected($civilite === $option->value)>{{ $option->libelle() }}</option>
                                @endforeach
                            </select>
                            @error('civilite') <div class="erreur">{{ $message }}</div> @enderror
                        </div>
                        <x-champ nom="prenom" libelle="Prénom" requis autocomplete="off" />
                        <x-champ nom="nom" libelle="Nom" requis autocomplete="off" />
                    </div>
                </div>
            </section>

            <section class="carte">
                <h2>Données privées</h2>
                <p class="discret">Jamais affichées publiquement, sauf le mobile si la case ci-dessous est cochée. Le n° AVS est chiffré dans la base de données.</p>
                <div class="grille">
                    <x-champ nom="rue" libelle="Rue" />
                    <x-champ nom="numero" libelle="N°" />
                    <x-champ nom="npa" libelle="Code postal" inputmode="numeric" />
                    <x-champ nom="localite" libelle="Localité" />
                    <x-champ-mobile requis />
                    <x-champ nom="email_prive" libelle="E-mail privé" type="email" requis />
                    <x-champ nom="avs" libelle="N° AVS" placeholder="756.1234.5678.97" autocomplete="off" />
                    <x-champ nom="date_naissance" libelle="Date de naissance" type="date" />
                </div>
            </section>

            <section class="carte">
                <h2>Société</h2>
                <div class="grille">
                    <div @class(['champ', 'champ--erreur' => $errors->has('identifiant_email')])>
                        <label for="champ-identifiant_email">E-mail société</label>
                        <div class="groupe-email">
                            <input id="champ-identifiant_email" type="text" wire:model="identifiant_email"
                                   placeholder="prenom.nom" autocomplete="off" autocapitalize="none" spellcheck="false">
                            <span class="groupe-email__domaine">{{ '@'.\App\Livewire\Membres\FicheMembre::domaineEmail() }}</span>
                        </div>
                        @if ($emailHorsDomaine)
                            <div class="aide">Adresse actuelle : <strong>{{ $emailHorsDomaine }}</strong>. Elle est conservée tant que vous ne saisissez pas d'identifiant {{ '@'.\App\Livewire\Membres\FicheMembre::domaineEmail() }}.</div>
                        @else
                            <div class="aide">Sert d'identifiant de connexion. Sans e-mail société, le membre ne peut pas se connecter.</div>
                        @endif
                        @error('identifiant_email') <div class="erreur">{{ $message }}</div> @enderror
                    </div>
                    <x-champ nom="date_entree" libelle="Date d'entrée" type="date" />
                    <x-champ nom="date_sortie" libelle="Date de sortie" type="date" aide="Laisser vide tant que la personne est membre." />
                </div>
            </section>

            <section class="carte">
                <h2>Autorisation <span aria-hidden="true">*</span></h2>
                <p class="discret">Chaque niveau a aussi les droits des niveaux précédents.</p>
                <div class="grille" role="radiogroup" aria-label="Autorisation">
                    @foreach ($tousLesRoles as $option)
                        <label class="case" wire:key="role-{{ $option->value }}">
                            <input type="radio" wire:model="role" value="{{ $option->value }}"
                                   @disabled($option === \App\Enums\Role::Administrateur && auth()->user()->cannot('attribuer-role-administrateur'))>
                            <span><strong>{{ $option->libelle() }}</strong><br><span class="discret">{{ $option->description() }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('role') <div class="champ"><div class="erreur">{{ $message }}</div></div> @enderror
            </section>

            <section class="carte">
                <h2>Affichage sur le site</h2>                <div class="grille">
                    <label class="case"><input type="checkbox" wire:model="afficher_mobile"> Afficher le mobile</label>
                </div>
            </section>
        </fieldset>

        @if ($enEdition)
            <div class="actions bloc">
                <button type="submit" class="bouton" wire:loading.attr="disabled" wire:target="photo,enregistrer">
                    <span wire:loading.remove wire:target="enregistrer">Enregistrer</span>
                    <span wire:loading wire:target="enregistrer">Enregistrement…</span>
                </button>
                <button type="button" class="bouton bouton--secondaire" x-on:click="oublier()" wire:click="annuler">Annuler</button>
            </div>
            <p class="discret">* champ obligatoire</p>
        @endif
    </form>

    @if ($membre?->email && ! $enEdition)
        <section class="carte">
            <h2>Compte de connexion</h2>
            <p class="discret">
                {{ $membre->password ? 'Mot de passe choisi' : 'Mot de passe pas encore choisi' }}
                · Dernière connexion : {{ $membre->derniere_connexion_le?->format('d.m.Y à H:i') ?? 'jamais' }}
            </p>
            <button type="button" class="bouton bouton--secondaire" wire:click="envoyerLienMotDePasse"
                    wire:confirm="Envoyer à {{ $membre->email }} un lien pour choisir son mot de passe ?">
                Envoyer un lien pour choisir le mot de passe
            </button>
        </section>
    @endif

    <p><a href="{{ route('membres.index') }}">← Retour à la liste des membres</a></p>
</div>
