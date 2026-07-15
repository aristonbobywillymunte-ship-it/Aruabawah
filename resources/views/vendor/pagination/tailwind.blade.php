@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="flex items-center justify-between gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-400">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-400">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:items-center sm:justify-between">
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

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="inline-flex h-9 items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-400">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl bg-[#1fa387] px-3 text-sm font-bold text-white shadow-sm">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl px-3 text-sm font-semibold text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

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
        </div>
    </nav>
@endif
