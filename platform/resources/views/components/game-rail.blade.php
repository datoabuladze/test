@props(['title', 'games', 'icon' => null, 'href' => null, 'size' => 'md'])
@if ($games->isNotEmpty())
<section {{ $attributes->merge(['class' => 'py-4']) }} x-data="rail">
    <div class="mb-3 flex items-end justify-between gap-4">
        <h2 class="flex items-center gap-2 text-lg font-bold sm:text-xl">
            @if ($icon)<span class="flex size-8 items-center justify-center rounded-lg bg-brand/15 text-brand"><x-icon :name="$icon" class="size-4.5"/></span>@endif
            {{ $title }}
        </h2>
        <div class="flex items-center gap-2">
            @if ($href)
                <a href="{{ $href }}" class="text-sm font-medium text-ink-2 hover:text-ink">{{ __('See all') }}</a>
            @endif
            <button type="button" class="hidden size-8 items-center justify-center rounded-lg border border-line bg-card text-ink-2 transition hover:text-ink disabled:opacity-30 sm:flex"
                    @click="left" :disabled="!canLeft" aria-label="{{ __('Scroll left') }}"><x-icon name="chevron-left" class="size-4"/></button>
            <button type="button" class="hidden size-8 items-center justify-center rounded-lg border border-line bg-card text-ink-2 transition hover:text-ink disabled:opacity-30 sm:flex"
                    @click="right" :disabled="!canRight" aria-label="{{ __('Scroll right') }}"><x-icon name="chevron-right" class="size-4"/></button>
        </div>
    </div>
    <div class="rail" x-ref="track">
        @foreach ($games as $game)
            <x-game-card :game="$game" :size="$size" />
        @endforeach
    </div>
</section>
@endif
