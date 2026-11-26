<div>
    <h1>Mon profil</h1>

    <section class="carte">
        <h2>Mes informations</h2>
        <p class="discret">Ces informations sont gérées par le comité. Si quelque chose est faux, contactez-le.</p>
        <dl class="infos">
            <dt>Nom</dt>
            <dd>{{ $membre->civilite?->libelle() }} {{ $membre->nomComplet() }}
                @if ($membre->surnom) <span class="discret">« {{ $membre->surnom }} »</span> @endif
            </dd>

            <dt>E-mail société</dt>
            <dd>{{ $membre->email }}</dd>

            <dt>Rôles</dt>
            <dd>
                @forelse ($membre->roles as $userRole)
                    <span class="badge badge--{{ $userRole->role->value }}">{{ $userRole->role->libelle() }}</span>
                @empty
                    Membre
                @endforelse
            </dd>

            <dt>Membre depuis</dt>
            <dd>{{ $membre->date_entree?->format('d.m.Y') ?? '—' }}</dd>

            <dt>Date de naissance</dt>
            <dd>{{ $membre->date_naissance?->format('d.m.Y') ?? '—' }}</dd>

            <dt>N° AVS</dt>
            <dd>{{ $avsMasque ?? '—' }}</dd>
        </dl>
    </section>

    <form wire:submit="enregistrerCoordonnees" novalidate>
        <section class="carte">
            <h2>Mes coordonnées privées</h2>
            <p class="discret">Elles servent au comité et à vos moniteurs. Elles ne sont jamais affichées publiquement, sauf si vous le choisissez ci-dessous.</p>
            <div class="grille">
                <x-champ nom="rue" libelle="Rue" autocomplete="address-line1" />
                <x-champ nom="numero" libelle="N°" />
                <x-champ nom="npa" libelle="Code postal" inputmode="numeric" autocomplete="postal-code" />
                <x-champ nom="localite" libelle="Localité" autocomplete="address-level2" />
                <x-champ nom="telephone" libelle="Téléphone" type="tel" autocomplete="tel" />
                <x-champ nom="mobile" libelle="Mobile" type="tel" autocomplete="tel" />
                <x-champ nom="email_prive" libelle="E-mail privé" type="email" autocomplete="email" />
            </div>
        </section>

        <section class="carte">
            <h2>Ce que les visiteurs du site peuvent voir</h2>
            <p class="discret">Par exemple sur la page des moniteurs. Tout est masqué par défaut.</p>
            <div class="grille">
                <label class="case"><input type="checkbox" wire:model="afficher_photo"> Afficher ma photo</label>
                <label class="case"><input type="checkbox" wire:model="afficher_adresse"> Afficher mon adresse</label>
                <label class="case"><input type="checkbox" wire:model="afficher_telephone"> Afficher mon téléphone</label>
                <label class="case"><input type="checkbox" wire:model="afficher_mobile"> Afficher mon mobile</label>
            </div>
        </section>

        <div class="actions bloc">
            <button type="submit" class="bouton">
                <span wire:loading.remove wire:target="enregistrerCoordonnees">Enregistrer mes coordonnées</span>
                <span wire:loading wire:target="enregistrerCoordonnees">Enregistrement…</span>
            </button>
        </div>
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
