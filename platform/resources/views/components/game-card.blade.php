@props(['game', 'size' => 'md', 'eager' => false])
@php
    $sizeClass = match ($size) {
        'lg' => 'w-[min(78vw,340px)]',
        'sm' => 'w-[42vw] xs:w-40 sm:w-44',
        'grid' => 'w-full',
        default => 'w-[44vw] xs:w-44 sm:w-52 lg:w-56',
    };
@endphp
<a href="{{ $game->url() }}" {{ $attributes->merge(['class' => "game-card group shrink-0 snap-start $sizeClass"]) }}
   aria-label="{{ $game->tr('title') }}">
    <div class="game-card-media aspect-[4/3] overflow-hidden">
        <x-game-thumb :game="$game" :eager="$eager" />
    </div>
    <div class="pointer-events-none absolute inset-x-0 top-0 flex gap-1 p-2">
        @if ($game->isNew())
            <span class="badge bg-brand-2 text-black">{{ __('New') }}</span>
        @endif
        @if ($game->is_multiplayer)
            <span class="badge bg-brand-3 text-white">2P</span>
        @endif
        @if ($game->engine === \App\Enums\GameEngine::Ruffle)
            <span class="badge bg-amber-400 text-black">Flash</span>
        @endif
    </div>
    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/50 to-transparent p-2.5 pt-8">
        <h3 class="truncate text-sm font-semibold text-white">{{ $game->tr('title') }}</h3>
        <div class="mt-0.5 flex items-center gap-2 text-[11px] text-white/70">
            @if ($game->rating_count > 0)
                <span class="inline-flex items-center gap-0.5"><x-icon name="star" class="size-3 fill-amber-300 text-amber-300"/>{{ number_format($game->rating_avg, 1) }}</span>
            @endif
            <span class="inline-flex items-center gap-0.5"><x-icon name="play" class="size-3"/>{{ \Illuminate\Support\Number::abbreviate($game->play_count) }}</span>
            @if ($game->is_mobile_friendly)
                <span class="inline-flex items-center" title="{{ __('Mobile friendly') }}"><x-icon name="device" class="size-3"/></span>
            @endif
        </div>
    </div>
    <span class="absolute inset-0 flex items-center justify-center opacity-0 transition group-hover:opacity-100">
        <span class="flex size-12 items-center justify-center rounded-full bg-white/95 text-black shadow-xl"><x-icon name="play" class="size-5 fill-current"/></span>
    </span>
</a>
