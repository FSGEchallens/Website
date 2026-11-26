<?php

namespace App\Livewire\Membres;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Membres')]
class ListeMembres extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'anciens', except: false)]
    public bool $inclureAnciens = false;

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function updatedInclureAnciens(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $membres = User::query()
            ->recherche($this->recherche)
            ->when(! $this->inclureAnciens, fn ($query) => $query->where(
                fn ($q) => $q->whereNull('date_sortie')->orWhere('date_sortie', '>', now()->toDateString())
            ))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(25);

        return view('livewire.membres.liste-membres', ['membres' => $membres]);
    }
}
