<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Bloc de texte du site modifiable par le comité (ex. « accueil.texte »).
 * `contenu` contient du HTML déjà nettoyé par App\Support\NettoyeurHtml.
 */
#[Fillable(['titre', 'contenu', 'visible'])]
class BlocContenu extends Model
{
    use Journalise;

    protected $table = 'blocs_contenu';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avec_titre' => 'boolean',
            'masquable' => 'boolean',
            'visible' => 'boolean',
        ];
    }

    /**
     * Blocs demandés, indexés par clé.
     *
     * @param  array<int, string>  $cles
     * @return Collection<string, BlocContenu>
     */
    public static function parCles(array $cles): Collection
    {
        return static::whereIn('cle', $cles)->get()->keyBy('cle');
    }

    public function libelleJournal(): string
    {
        return $this->libelle;
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return ['titre' => 'Titre', 'contenu' => 'Texte', 'visible' => 'Affiché', 'fichier' => 'Fichier PDF'];
    }
}
