{{-- Pagination par liens classiques (pages sans Livewire). --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="discret">« Plus récents</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="bouton bouton--secondaire" rel="prev">« Plus récents</a>
        @endif

        <span class="discret">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="bouton bouton--secondaire" rel="next">Plus anciens »</a>
        @else
            <span class="discret">Plus anciens »</span>
        @endif
    </nav>
@endif
