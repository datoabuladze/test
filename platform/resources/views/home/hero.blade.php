@php $count = $games->count(); @endphp
<section class="pt-5 pb-3" x-data="carousel({{ $count }})" @mouseenter="stop" @mouseleave="start" aria-roledescription="carousel" aria-label="{{ $title }}">
    <div class="relative overflow-hidden rounded-3xl border border-line">
        @foreach ($games as $i => $game)
            <article class="relative grid min-h-[300px] items-end sm:min-h-[380px] lg:min-h-[420px] {{ $i === 0 ? '' : 'hidden' }}"
                     :class="{ 'hidden': !isCurrent({{ $i }}) }" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $count }}">
                <div class="absolute inset-0">
                    <x-game-thumb :game="$game" :eager="$i === 0" sizes="100vw" class="h-full w-full scale-105 object-cover blur-[1px]"/>
                    <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-black/10"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
                </div>
                <div class="relative grid gap-6 p-6 sm:p-10 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div class="max-w-xl animate-fade-up text-white">
                        <span class="badge bg-white/15 text-white backdrop-blur">{{ __('Featured') }}</span>
                        <h2 class="mt-3 text-3xl font-black leading-tight sm:text-5xl">{{ $game->tr('title') }}</h2>
                        <p class="mt-3 line-clamp-2 text-sm text-white/80 sm:text-base">{{ $game->tr('short_description') }}</p>
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a href="{{ $game->url() }}" class="btn-primary px-6 py-3 text-base"><x-icon name="play" class="size-4 fill-current"/>{{ __('Play now') }}</a>
                            @foreach ($game->categories->take(2) as $cat)
                                <a href="{{ $cat->url() }}" class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white backdrop-blur hover:bg-white/20">{{ $cat->tr('name') }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="hidden w-72 overflow-hidden rounded-2xl border border-white/20 shadow-2xl lg:block">
                        <div class="aspect-[4/3]"><x-game-thumb :game="$game" :eager="$i === 0" sizes="288px"/></div>
                    </div>
                </div>
            </article>
        @endforeach

        @if ($count > 1)
            <div class="absolute right-5 bottom-5 flex items-center gap-2">
                <button type="button" @click="prev" class="flex size-9 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur hover:bg-black/60" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="size-4"/></button>
                @foreach ($games as $i => $game)
                    <button type="button" @click="go({{ $i }})" class="h-1.5 rounded-full transition-all" :class="isCurrent({{ $i }}) ? 'w-6 bg-white' : 'w-1.5 bg-white/40'" aria-label="{{ __('Slide :n', ['n' => $i + 1]) }}"></button>
                @endforeach
                <button type="button" @click="next" class="flex size-9 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur hover:bg-black/60" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="size-4"/></button>
            </div>
        @endif
    </div>
</section>
