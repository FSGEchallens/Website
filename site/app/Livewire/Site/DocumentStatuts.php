<?php

namespace App\Livewire\Site;

use App\Models\BlocContenu;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Remplacement du PDF des statuts par le comité et l'administrateur.
 * L'ancien fichier est archivé dans le journal (restaurable).
 */
class DocumentStatuts extends Component
{
    use WithFileUploads;

    public const DISQUE = 'local';

    public const CHEMIN = 'documents/statuts.pdf';

    public ?TemporaryUploadedFile $fichier = null;

    public function mount(): void
    {
        $this->authorize('gerer-contenu');
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return ['fichier' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240']];
    }

    public function updatedFichier(): void
    {
        $this->validateOnly('fichier');
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-contenu');
        $this->validate();

        $contenu = (string) file_get_contents($this->fichier->getRealPath());

        if (! str_starts_with($contenu, '%PDF-')) {
            $this->addError('fichier', 'Ce fichier n’est pas un PDF valide.');

            return;
        }

        $bloc = BlocContenu::where('cle', 'societe.statuts')->firstOrFail();
        $bloc->archiverFichier('fichier', self::DISQUE, self::CHEMIN);

        Storage::disk(self::DISQUE)->put(self::CHEMIN, $contenu);

        $bloc->journaliserFichiersEnAttente();

        session()->flash('statuts_remplaces', true);
        $this->redirectRoute('societe.statuts');
    }

    public function render(): View
    {
        return view('livewire.site.document-statuts');
    }
}
