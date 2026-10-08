@props(['placement'])
@php
    $campaign = app(\App\Services\Ads::class)->forPlacement($placement);
@endphp
@if ($campaign)
<aside {{ $attributes->merge(['class' => 'flex flex-col items-center']) }} aria-label="{{ __('Advertisement') }}">
    <span class="mb-1 self-start text-[10px] uppercase tracking-wider text-ink-3">{{ __('Advertisement') }}</span>
    @if ($campaign->type === 'adsense' && config('platform.ads.adsense_client'))
        <div class="w-full" data-adsense-slot="{{ $campaign->adsense_slot }}" data-adsense-client="{{ config('platform.ads.adsense_client') }}"></div>
    @elseif ($campaign->type === 'sponsored_game' && $campaign->game)
        <a href="{{ route('ads.click', $campaign) }}" rel="sponsored" class="card flex w-full items-center gap-4 p-3">
            <div class="w-24 overflow-hidden rounded-lg"><div class="aspect-[4/3]"><x-game-thumb :game="$campaign->game"/></div></div>
            <div><div class="text-xs text-brand-2">{{ __('Sponsored') }}</div><div class="font-semibold">{{ $campaign->game->tr('title') }}</div></div>
        </a>
    @elseif ($campaign->image_path)
        <a href="{{ route('ads.click', $campaign) }}" rel="sponsored noopener" target="_blank" class="block max-w-full overflow-hidden rounded-xl">
            <img src="{{ asset('storage/'.$campaign->image_path) }}" alt="{{ $campaign->alt_text }}" loading="lazy" class="max-h-[250px] w-auto">
        </a>
    @endif
</aside>
@endif
