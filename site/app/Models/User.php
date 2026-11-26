<?php

namespace App\Models;

use App\Enums\Civilite;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Membre de la société.
 *
 * `email` = e-mail société (identifiant de connexion, facultatif).
 * `avs` est chiffré dans la base avec la clé APP_KEY.
 */
#[Fillable([
    'civilite', 'prenom', 'nom', 'surnom',
    'rue', 'numero', 'npa', 'localite', 'telephone', 'mobile', 'email_prive',
    'email', 'avs', 'date_naissance', 'date_entree', 'date_sortie',
    'afficher_photo', 'afficher_adresse', 'afficher_telephone', 'afficher_mobile',
    'compte_wp',
])]
#[Hidden(['password', 'remember_token', 'avs'])]
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
        ];
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
     * Recherche simple sur le prénom, le nom, le surnom, la localité et les e-mails.
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
            foreach (['prenom', 'nom', 'surnom', 'localite', 'email', 'email_prive'] as $colonne) {
                $q->orWhere($colonne, 'like', '%'.$terme.'%');
            }
        });
    }
}
