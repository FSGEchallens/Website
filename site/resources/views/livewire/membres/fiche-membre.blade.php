<div>
    <h1>{{ $membre ? 'Modifier '.$membre->nomComplet() : 'Nouveau membre' }}</h1>

    <form wire:submit="enregistrer" novalidate>
        <section class="carte">
            <h2>Identité</h2>
            <div class="grille">
                <div @class(['champ', 'champ--erreur' => $errors->has('civilite')])>
                    <label for="champ-civilite">Titre</label>
                    <select id="champ-civilite" wire:model="civilite">
                        <option value="">—</option>
                        @foreach ($civilites as $option)
                            <option value="{{ $option->value }}">{{ $option->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('civilite') <div class="erreur">{{ $message }}</div> @enderror
                </div>
                <x-champ nom="prenom" libelle="Prénom" requis autocomplete="off" />
                <x-champ nom="nom" libelle="Nom" requis autocomplete="off" />
                <x-champ nom="surnom" libelle="Surnom" aide="Facultatif, affiché à la place du prénom si renseigné." />
            </div>
        </section>

        <section class="carte">
            <h2>Coordonnées privées</h2>
            <div class="grille">
                <x-champ nom="rue" libelle="Rue" />
                <x-champ nom="numero" libelle="N°" />
                <x-champ nom="npa" libelle="Code postal" inputmode="numeric" />
                <x-champ nom="localite" libelle="Localité" />
                <x-champ nom="telephone" libelle="Téléphone" type="tel" />
                <x-champ nom="mobile" libelle="Mobile" type="tel" />
                <x-champ nom="email_prive" libelle="E-mail privé" type="email" />
            </div>
        </section>

        <section class="carte">
            <h2>Société</h2>
            <div class="grille">
                <x-champ nom="email" libelle="E-mail société" type="email"
                         aide="Sert d'identifiant de connexion. Sans e-mail société, le membre ne peut pas se connecter." />
                <x-champ nom="date_entree" libelle="Date d'entrée" type="date" />
                <x-champ nom="date_sortie" libelle="Date de sortie" type="date" aide="Laisser vide tant que la personne est membre." />
                <x-champ nom="compte_wp" libelle="Compte WordPress (ancien site)" />
            </div>

            @if ($membre?->email)
                <p class="discret">
                    Compte : {{ $membre->password ? 'mot de passe choisi' : 'mot de passe pas encore choisi' }}
                    · Dernière connexion : {{ $membre->derniere_connexion_le?->format('d.m.Y à H:i') ?? 'jamais' }}
                </p>
                <button type="button" class="bouton bouton--secondaire" wire:click="envoyerLienMotDePasse"
                        wire:confirm="Envoyer à {{ $membre->email }} un lien pour choisir son mot de passe ?">
                    Envoyer un lien pour choisir le mot de passe
                </button>
            @endif
        </section>

        <section class="carte">
            <h2>Autorisations</h2>
            <p class="discret">Une personne peut avoir plusieurs rôles. Un membre sans rôle peut seulement voir et modifier son propre profil.</p>
            <div class="grille">
                @foreach ($tousLesRoles as $role)
                    <label class="case" wire:key="role-{{ $role->value }}">
                        <input type="checkbox" wire:model="roles" value="{{ $role->value }}"
                               @disabled($role === \App\Enums\Role::Administrateur && auth()->user()->cannot('attribuer-role-administrateur'))>
                        <span><strong>{{ $role->libelle() }}</strong><br><span class="discret">{{ $role->description() }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('roles') <div class="champ"><div class="erreur">{{ $message }}</div></div> @enderror
            @error('roles.*') <div class="champ"><div class="erreur">{{ $message }}</div></div> @enderror
        </section>

        <section class="carte">
            <h2>Données sensibles (J+S)</h2>
            <p class="discret">Visibles uniquement par le comité, le webmaster et le membre lui-même. Le n° AVS est chiffré dans la base de données.</p>
            <div class="grille">
                <x-champ nom="avs" libelle="N° AVS" placeholder="756.1234.5678.97" autocomplete="off" />
                <x-champ nom="date_naissance" libelle="Date de naissance" type="date" />
            </div>
        </section>

        <section class="carte">
            <h2>Affichage sur le site</h2>
            <p class="discret">Ce que les autres visiteurs pourront voir de ce membre (par exemple sur la page des moniteurs).</p>
            <div class="grille">
                <label class="case"><input type="checkbox" wire:model="afficher_photo"> Afficher la photo</label>
                <label class="case"><input type="checkbox" wire:model="afficher_adresse"> Afficher l'adresse</label>
                <label class="case"><input type="checkbox" wire:model="afficher_telephone"> Afficher le téléphone</label>
                <label class="case"><input type="checkbox" wire:model="afficher_mobile"> Afficher le mobile</label>
            </div>
        </section>

        <div class="actions">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="enregistrer">Enregistrer</span>
                <span wire:loading wire:target="enregistrer">Enregistrement…</span>
            </button>
            <a href="{{ route('membres.index') }}" class="bouton bouton--secondaire">Annuler</a>
        </div>
    </form>
</div>
