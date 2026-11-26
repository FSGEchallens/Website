<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Une entrée du journal des modifications.
 *
 * @property array<string, mixed>|null $anciennes_valeurs
 * @property array<string, mixed>|null $nouvelles_valeurs
 * @property array<int, array{attribut: string, disque: string, chemin: string, archive: string}>|null $fichiers
 */
class JournalModification extends Model
{
    use Prunable;

    public const CREATION = 'creation';

    public const MODIFICATION = 'modification';

    public const SUPPRESSION = 'suppression';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anciennes_valeurs' => 'array',
            'nouvelles_valeurs' => 'array',
            'fichiers' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }

    public function libelleAction(): string
    {
        return match ($this->action) {
            self::CREATION => 'Création',
            self::MODIFICATION => 'Modification',
            self::SUPPRESSION => 'Suppression',
            default => ucfirst($this->action),
        };
    }

    /**
     * Les créations ne se restaurent pas (il suffit de supprimer l'élément).
     */
    public function estRestaurable(): bool
    {
        return in_array($this->action, [self::MODIFICATION, self::SUPPRESSION], true)
            && class_exists($this->sujet_type);
    }

    /**
     * Entrées plus anciennes que la durée de conservation (nettoyage quotidien : model:prune).
     *
     * @return Builder<JournalModification>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('journal.conservation_jours')));
    }

    /**
     * Supprime aussi les fichiers archivés de l'entrée.
     */
    protected function pruning(): void
    {
        foreach ($this->fichiers ?? [] as $fichier) {
            Storage::disk('local')->delete($fichier['archive']);
        }
    }
}
