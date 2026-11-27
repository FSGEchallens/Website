<?php

namespace App\Http\Controllers;

use App\Livewire\Site\DocumentStatuts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Page « Statuts » : le PDF des statuts affiché dans la page, avec un bouton de téléchargement.
 */
class StatutsController extends Controller
{
    public function page(): View
    {
        $disque = Storage::disk(DocumentStatuts::DISQUE);
        $existe = $disque->exists(DocumentStatuts::CHEMIN);

        return view('statuts', [
            'existe' => $existe,
            'version' => $existe ? $disque->lastModified(DocumentStatuts::CHEMIN) : null,
        ]);
    }

    public function pdf(): BinaryFileResponse
    {
        $disque = Storage::disk(DocumentStatuts::DISQUE);
        abort_unless($disque->exists(DocumentStatuts::CHEMIN), 404);

        $disposition = request()->boolean('telecharger') ? 'attachment' : 'inline';

        return response()->file($disque->path(DocumentStatuts::CHEMIN), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="FSG-Echallens-Statuts.pdf"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
