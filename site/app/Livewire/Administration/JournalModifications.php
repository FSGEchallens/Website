<?php

namespace App\Livewire\Administration;

use App\Models\BlocContenu;
use App\Models\JournalModification;
use App\Models\Partenaire;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Journal des modifications : qui a modifié quoi, avec restauration. Réservé à l'administrateur.
 */
#[Title('Journal des modifications')]
class JournalModifications extends Component
{
    use WithPagination;

    /** Types d'éléments suivis par le journal (classe => libellé). */
    public const TYPES = [
        User::class => 'Membres',
        Partenaire::class => 'Partenaires',
        BlocContenu::class => 'Textes du site',
    ];

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'action', except: '')]
    public string $action = '';

    public ?int $ouverte = null;

    public ?string $confirmation = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        $this->authorize('consulter-journal');
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'type', 'action'], true)) {
            $this->resetPage();
        }
    }

    public function basculer(int $id): void
    {
        $this->ouverte = $this->ouverte === $id ? null : $id;
    }

    public function restaurer(int $id, Journal $journal): void
    {
        $this->authorize('consulter-journal');
        $entree = JournalModification::findOrFail($id);
        $this->reset('confirmation', 'erreur');

        try {
            $journal->restaurer($entree);
        } catch (RuntimeException $exception) {
            $this->erreur = $exception->getMessage();

            return;
        } catch (QueryException) {
            $this->erreur = 'La restauration est impossible : une valeur est déjà utilisée ailleurs (par exemple la même adresse e-mail société).';

            return;
        }

        $this->confirmation = 'Restauration effectuée pour '.$entree->libelle.' (entrée n° '.$entree->id.'). Elle figure maintenant en tête du journal.';
        $this->ouverte = null;
        $this->resetPage();
    }

    /**
     * Valeur lisible pour l'affichage (les champs masqués ne sont jamais affichés en clair).
     */
    public static function formaterValeur(string $champ, mixed $valeur): string
    {
        if (in_array($champ, config('journal.champs_sans_valeur'), true)) {
            return '(modifié)';
        }

        if (in_array($champ, config('journal.champs_masques'), true)) {
            return $valeur === null ? '—' : '•••• (masqué)';
        }

        return match (true) {
            $valeur === null, $valeur === '' => '—',
            is_bool($valeur) => $valeur ? 'Oui' : 'Non',
            in_array($champ, ['visible', 'afficher_mobile'], true) => $valeur ? 'Oui' : 'Non',
            // Les textes mis en forme sont affichés sans leurs balises.
            default => mb_strimwidth(trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ' ', (string) $valeur)), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 160, '…'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChamps(string $classe): array
    {
        return method_exists($classe, 'libellesChampsJournal') ? $classe::libellesChampsJournal() : [];
    }

    public function render(): View
    {
        $terme = trim($this->recherche);

        $entrees = JournalModification::query()
            ->with('auteur')
            ->when($terme !== '', fn ($q) => $q->where('libelle', 'like', '%'.$terme.'%'))
            ->when(array_key_exists($this->type, self::TYPES), fn ($q) => $q->where('sujet_type', $this->type))
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->latest('id')
            ->paginate(30);

        return view('livewire.administration.journal-modifications', [
            'entrees' => $entrees,
            'types' => self::TYPES,
            'conservation' => (int) config('journal.conservation_jours'),
        ]);
    }
}
