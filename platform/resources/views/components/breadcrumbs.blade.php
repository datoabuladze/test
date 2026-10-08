@props(['items'])
{{-- $items: list of [label, url|null] --}}
<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->merge(['class' => 'text-xs text-ink-3']) }}>
    <ol class="flex flex-wrap items-center gap-1">
        @foreach ($items as $i => [$label, $url])
            <li class="flex items-center gap-1">
                @if ($i > 0)<x-icon name="chevron-right" class="size-3"/>@endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="hover:text-ink">{{ $label }}</a>
                @else
                    <span class="text-ink-2" @if($loop->last) aria-current="page" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($items)->values()->map(fn ($item, $i) => array_filter([
        '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1] ?? url()->current(),
    ]))->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
