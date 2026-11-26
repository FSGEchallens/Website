<?php

namespace App\Models;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Notifications\LienMotDePasse;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Membre de la société.
 *
 * `email` = e-mail société (identifiant de connexion, facultatif).
 * `avs` est chiffré dans la base avec la clé APP_KEY.
 */
#[Fillable([
    'civilite', 'prenom', 'nom',
    'rue', 'numero', 'npa', 'localite', 'telephone', 'mobile', 'email_prive',
    'email', 'avs', 'date_naissance', 'date_entree', 'date_sortie',
    'afficher_photo', 'afficher_adresse', 'afficher_telephone', 'afficher_mobile',
    'compte_wp',
])]
#[Hidden(['password', 'remember_token', 'avs', 'code_connexion'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'civilite' => Civilite::class,
            'avs' => 'encrypted',
            'date_naissance' => 'date',
            'date_entree' => 'date',
            'date_sortie' => 'date',
            'afficher_photo' => 'boolean',
            'afficher_adresse' => 'boolean',
            'afficher_telephone' => 'boolean',
            'afficher_mobile' => 'boolean',
            'code_connexion_expire_le' => 'datetime',
            'derniere_connexion_le' => 'datetime',
        ];
    }

    /**
     * Crée un nouveau code de connexion à 6 chiffres (stocké haché) et le renvoie en clair pour l'e-mail.
     */
    public function genererCodeConnexion(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'code_connexion' => Hash::make($code),
            'code_connexion_expire_le' => now()->addMinutes((int) config('auth.code_connexion.validite_minutes')),
            'code_connexion_tentatives' => 0,
        ])->save();

        return $code;
    }

    public function aUnCodeConnexionValide(): bool
    {
        return $this->code_connexion !== null
            && $this->code_connexion_expire_le?->isFuture()
            && $this->code_connexion_tentatives < (int) config('auth.code_connexion.tentatives_max');
    }

    /**
     * Vérifie le code saisi. Chaque essai compte ; le code est effacé dès qu'il a servi.
     */
    public function verifierCodeConnexion(string $code): bool
    {
        if (! $this->aUnCodeConnexionValide()) {
            return false;
        }

        $this->increment('code_connexion_tentatives');

        if (! Hash::check(trim($code), $this->code_connexion)) {
            return false;
        }

        $this->effacerCodeConnexion();

        return true;
    }

    public function effacerCodeConnexion(): void
    {
        $this->forceFill([
            'code_connexion' => null,
            'code_connexion_expire_le' => null,
            'code_connexion_tentatives' => 0,
        ])->save();
    }

    /**
     * E-mail « mot de passe oublié » en français.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new LienMotDePasse($token, invitation: false));
    }

    /**
     * Envoie au membre un lien (valable 7 jours) pour choisir son premier mot de passe.
     */
    public function envoyerInvitation(): void
    {
        if ($this->email === null) {
            return;
        }

        $token = Password::broker('invitations')->createToken($this);

        $this->notify(new LienMotDePasse($token, invitation: true));
    }

    public function peutGererLesMembres(): bool
    {
        return $this->aRole(Role::Administrateur, Role::Comite);
    }

    public function estAdministrateur(): bool
    {
        return $this->aRole(Role::Administrateur);
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function aRole(Role ...$roles): bool
    {
        return $this->roles->contains(fn (UserRole $userRole) => in_array($userRole->role, $roles, true));
    }

    /**
     * Remplace les rôles du membre par ceux donnés.
     *
     * @param  array<int, Role>  $roles
     */
    public function synchroniserRoles(array $roles): void
    {
        $valeurs = array_map(fn (Role $role) => $role->value, $roles);

        $this->roles()->whereNotIn('role', $valeurs)->delete();

        foreach ($roles as $role) {
            $this->roles()->firstOrCreate(['role' => $role]);
        }

        $this->unsetRelation('roles');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAvecRole(Builder $query, Role $role): void
    {
        $query->whereHas('roles', fn (Builder $q) => $q->where('role', $role->value));
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function estActif(): bool
    {
        return $this->date_sortie === null || $this->date_sortie->isFuture();
    }

    /**
     * Recherche simple sur le prénom, le nom, la localité et les e-mails.
     *
     * @param  Builder<User>  $query
     */
    public function scopeRecherche(Builder $query, ?string $terme): void
    {
        $terme = trim((string) $terme);

        if ($terme === '') {
            return;
        }

        $query->where(function (Builder $q) use ($terme): void {
            foreach (['prenom', 'nom', 'localite', 'email', 'email_prive'] as $colonne) {
                $q->orWhere($colonne, 'like', '%'.$terme.'%');
            }
        });
    }
}
