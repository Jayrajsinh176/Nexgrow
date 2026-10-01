{{-- Dark-theme pagination for the Nexgrow dashboard: $members->links('pagination.nx') --}}
@if ($paginator->hasPages())
    @php
        $base = 'flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm transition';
        $idle = 'border-white/10 text-slate-300 hover:border-nx-gold/60 hover:text-white';
        $off = 'cursor-not-allowed border-white/5 text-slate-600';
    @endphp
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center gap-1.5">
        @if ($paginator->onFirstPage())
            <span class="{{ $base }} {{ $off }}" aria-disabled="true">&lsaquo; Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} {{ $idle }}">&lsaquo; Prev</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="{{ $base }} border-transparent text-slate-500">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="{{ $base }} border-nx-gold/70 bg-nx-gold/15 font-semibold text-nx-gold shadow-[0_0_12px_-4px] shadow-nx-gold">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $base }} {{ $idle }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} {{ $idle }}">Next &rsaquo;</a>
        @else
            <span class="{{ $base }} {{ $off }}" aria-disabled="true">Next &rsaquo;</span>
        @endif
    </nav>
@endif
