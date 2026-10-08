<section class="py-4">
    <div class="card relative overflow-hidden p-5 sm:p-6">
        <div class="absolute -right-10 -top-10 size-48 rounded-full bg-brand/25 blur-3xl"></div>
        <div class="relative flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <div class="w-40 shrink-0 overflow-hidden rounded-xl"><div class="aspect-[4/3]"><x-game-thumb :game="$game"/></div></div>
            <div class="flex-1">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-2"><x-icon name="dice" class="size-4"/>{{ $title }}</div>
                <h2 class="mt-1 text-xl font-bold">{{ $game->tr('title') }}</h2>
                <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ $game->tr('short_description') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ $game->url() }}" class="btn-primary"><x-icon name="play" class="size-4 fill-current"/>{{ __('Play') }}</a>
                <a href="{{ route('games.random') }}" class="btn-ghost"><x-icon name="refresh" class="size-4"/>{{ __('Surprise me') }}</a>
            </div>
        </div>
    </div>
</section>
