@if ($paginator->hasPages())
<nav role="navigation" aria-label="{{ __('Pagination') }}" class="mt-8 flex flex-wrap items-center justify-center gap-1.5">
    @if ($paginator->onFirstPage())
        <span class="btn-ghost btn-sm opacity-40"><x-icon name="chevron-left" class="size-4"/></span>
    @else
        <a class="btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="size-4"/></a>
    @endif
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="px-2 text-ink-3">…</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="btn-primary btn-sm min-w-9" aria-current="page">{{ $page }}</span>
                @else
                    <a class="btn-ghost btn-sm min-w-9" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())
        <a class="btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="size-4"/></a>
    @else
        <span class="btn-ghost btn-sm opacity-40"><x-icon name="chevron-right" class="size-4"/></span>
    @endif
</nav>
@endif
