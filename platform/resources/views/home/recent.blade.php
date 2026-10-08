<section class="py-4" x-data="recentRail" data-url="{{ route('api.games.by-ids') }}" x-show="!empty" x-cloak>
    <div class="mb-3 flex items-end justify-between">
        <h2 class="flex items-center gap-2 text-lg font-bold sm:text-xl">
            <span class="flex size-8 items-center justify-center rounded-lg bg-brand/15 text-brand"><x-icon name="history" class="size-4.5"/></span>{{ $title }}
        </h2>
        <button type="button" class="text-sm text-ink-3 hover:text-ink" @click="clear">{{ __('Clear') }}</button>
    </div>
    <div x-show="!loaded"><x-skeleton-rail/></div>
    <div x-ref="target"></div>
</section>
