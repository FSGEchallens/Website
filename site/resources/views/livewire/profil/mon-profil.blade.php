<div x-data="protectionModifications" x-on:input="marquerModifie()" x-on:change="marquerModifie()" x-on:submit="avantEnregistrement()">
    <div class="barre">
        <h1>Mon profil</h1>
        @unless ($enEdition)
            <button type="button" class="bouton" wire:click="modifier">Modifier</button>
        @endunless
    </div>

    @unless ($enEdition)
        <p class="alerte alerte--info">Profil verrouillé pour éviter une modification involontaire. Cliquez sur <strong>Modifier</strong> pour changer votre photo ou vos coordonnées.</p>
    @endunless

    <section class="carte">
        <h2>Mes informations</h2>
        <div class="identite">
            <x-champ-photo :url="$urlApercu" :a-une-photo="$aUnePhoto" :modifiable="$enEdition" alt="Ma photo" />

            <div>
                <dl class="infos">
                    <dt>Nom</dt>
                    <dd>{{ $membre->civilite?->libelle() }} {{ $membre->nomComplet() }}</dd>

                    <dt>E-mail société</dt>
                    <dd>{{ $membre->email }}</dd>

                    <dt>Autorisation</dt>
                    <dd>
                        @php($role = $membre->role ?? \App\Enums\Role::Membre)
                        <span class="badge badge--{{ $role->value }}">{{ $role->libelle() }}</span>
                    </dd>

                    <dt>Membre depuis</dt>
                    <dd>{{ $membre->date_entree?->format('d.m.Y') ?? '—' }}</dd>

                    <dt>Date de naissance</dt>
                    <dd>{{ $membre->date_naissance?->format('d.m.Y') ?? '—' }}</dd>

                    <dt>N° AVS</dt>
                    <dd>{{ $avsMasque ?? '—' }}</dd>
                </dl>
                <p class="discret">Ces informations sont gérées par le comité. Si quelque chose est faux, contactez-le.</p>
            </div>
        </div>
    </section>

    <form wire:submit="enregistrerCoordonnees" novalidate>
        <fieldset class="verrouillable" @disabled(! $enEdition)>
            <legend class="visuellement-cache">Mes coordonnées</legend>

            <section class="carte">
                <h2>Mes coordonnées privées</h2>
                <p class="discret">Elles servent au comité et à vos moniteurs. Elles ne sont jamais affichées publiquement, sauf votre mobile si vous le choisissez ci-dessous.</p>
                <div class="grille">
                    <x-champ nom="rue" libelle="Rue" autocomplete="address-line1" />
                    <x-champ nom="numero" libelle="N°" />
                    <x-champ nom="npa" libelle="Code postal" inputmode="numeric" autocomplete="postal-code" />
                    <x-champ nom="localite" libelle="Localité" autocomplete="address-level2" />
                    <x-champ nom="mobile" libelle="Mobile" type="tel" autocomplete="tel" requis />
                    <x-champ nom="email_prive" libelle="E-mail privé" type="email" autocomplete="email" requis />
                </div>
            </section>

            <section class="carte">
                <h2>Ce que les visiteurs du site peuvent voir</h2>                <div class="grille">
                    <label class="case"><input type="checkbox" wire:model="afficher_mobile"> Afficher mon mobile</label>
                </div>
            </section>
        </fieldset>

        @if ($enEdition)
            <div class="actions bloc">
                <button type="submit" class="bouton" wire:loading.attr="disabled" wire:target="photo,enregistrerCoordonnees">
                    <span wire:loading.remove wire:target="enregistrerCoordonnees">Enregistrer</span>
                    <span wire:loading wire:target="enregistrerCoordonnees">Enregistrement…</span>
                </button>
                <button type="button" class="bouton bouton--secondaire" x-on:click="oublier()" wire:click="annuler">Annuler</button>
            </div>
        @endif
    </form>

    <section class="carte carte--mot-de-passe">
        <h2>Changer mon mot de passe</h2>
        <form wire:submit="changerMotDePasse" novalidate>
            <x-champ nom="mot_de_passe_actuel" libelle="Mot de passe actuel" type="password" autocomplete="current-password" />

            <x-nouveau-mot-de-passe nom="nouveau_mot_de_passe" />

            <div class="actions">
                <button type="submit" class="bouton">
                    <span wire:loading.remove wire:target="changerMotDePasse">Changer le mot de passe</span>
                    <span wire:loading wire:target="changerMotDePasse">Enregistrement…</span>
                </button>
            </div>
        </form>
    </section>
</div>
