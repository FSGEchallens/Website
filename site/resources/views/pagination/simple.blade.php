@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="discret">« Précédent</span>
        @else
            <button type="button" class="bouton bouton--secondaire" wire:click="previousPage('{{ $paginator->getPageName() }}')">« Précédent</button>
        @endif

        <span class="discret">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <button type="button" class="bouton bouton--secondaire" wire:click="nextPage('{{ $paginator->getPageName() }}')">Suivant »</button>
        @else
            <span class="discret">Suivant »</span>
        @endif
    </nav>
@endif
