@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-3">
        <div class="text-sm text-slate-500">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-semibold text-slate-700">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            {!! __('of') !!}
            <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </div>

        <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-300">
                    ‹
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700">
                    ‹
                </a>
            @endif

            <span class="inline-flex h-9 items-center justify-center rounded-xl bg-[#1fa387] px-3 text-sm font-bold text-white shadow-sm">
                {{ $paginator->currentPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700">
                    ›
                </a>
            @else
                <span class="inline-flex h-9 items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-300">
                    ›
                </span>
            @endif
        </div>
    </nav>
@endif
