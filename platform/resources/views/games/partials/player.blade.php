{{-- Universal sandboxed player. Vars: $game, $frameUrl, $title, $ratio, $preview (bool: no analytics/scores). --}}
@php $preview = $preview ?? false; @endphp
<div x-data="gamePlayer"
     data-frame-url="{{ $frameUrl }}"
     data-engine="{{ $game->engine->value }}"
     data-game-id="{{ $game->id }}"
     data-play-url="{{ $preview ? '' : route('api.plays.start', $game) }}"
     data-score-mode="{{ $game->score_mode }}"
     data-score-session-url="{{ ! $preview && auth()->check() && $game->score_mode !== 'none' ? route('api.scores.session', $game) : '' }}"
     data-score-url="{{ ! $preview && auth()->check() && $game->score_mode !== 'none' ? route('api.scores.store', $game) : '' }}"
     data-orientation="{{ $game->orientation }}"
     data-keyboard-only="{{ $game->requiresKeyboard() ? '1' : '0' }}"
     data-sandbox="{{ config('platform.iframe_sandbox') }}"
     data-allow="{{ config('platform.iframe_allow') }}"
     data-title="{{ $title }}"
     class="overflow-hidden rounded-2xl border border-line bg-black shadow-2xl"
     :class="{ 'fixed inset-0 z-[60] rounded-none border-0': pseudoFullscreen }">
    <div x-ref="stage" class="relative w-full bg-black" style="aspect-ratio: {{ $ratio }}; max-height: calc(100dvh - 9rem);"
         :class="{ '!max-h-none h-[calc(100dvh-3rem)]': pseudoFullscreen || isFullscreen }">
        <div x-ref="frameHost" class="absolute inset-0"></div>

        {{-- Cover (shown until the visitor presses Play; the game is not loaded before that) --}}
        <div x-show="state === 'idle'" class="absolute inset-0">
            <x-game-thumb :game="$game" :eager="true" sizes="(min-width: 1280px) 900px, 100vw" class="h-full w-full object-cover opacity-50 blur-sm"/>
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/40 p-4 text-center text-white">
                <div class="w-36 overflow-hidden rounded-2xl border border-white/20 shadow-2xl sm:w-48"><div class="aspect-[4/3]"><x-game-thumb :game="$game" :eager="true" sizes="192px"/></div></div>
                <h1 class="text-2xl font-black sm:text-3xl">{{ $title }}</h1>
                <button type="button" @click="play" class="btn-primary px-8 py-3.5 text-base" data-testid="play-button">
                    <x-icon name="play" class="size-5 fill-current"/>{{ __('Play now') }}
                </button>
                <template x-if="keyboardWarning">
                    <p class="max-w-sm rounded-lg bg-amber-500/20 px-3 py-2 text-xs text-amber-200"><x-icon name="keyboard" class="inline size-4"/> {{ __('This game needs a keyboard and may not be playable on a touch-only device.') }}</p>
                </template>
            </div>
        </div>

        {{-- Loading --}}
        <div x-cloak x-show="state === 'loading'" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-bg text-ink">
            <div class="relative size-16">
                <div class="absolute inset-0 animate-spin rounded-full border-4 border-line border-t-brand"></div>
                <div class="absolute inset-3 animate-pulse rounded-full bg-brand/30"></div>
            </div>
            <p class="text-sm text-ink-2">{{ __('Loading :title…', ['title' => $title]) }}</p>
        </div>

        {{-- Error --}}
        <div x-cloak x-show="state === 'error'" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-bg p-6 text-center">
            <x-icon name="alert" class="size-10 text-warn"/>
            <p class="font-semibold">{{ __('This game did not load.') }}</p>
            <p class="max-w-sm text-sm text-ink-2" x-text="errorMessage"></p>
            <div class="flex gap-2"><button type="button" class="btn-primary" @click="restart">{{ __('Try again') }}</button></div>
        </div>

        {{-- Rotate hint --}}
        <div x-cloak x-show="rotateHint" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/85 p-6 text-center text-white">
            <x-icon name="rotate" class="size-12 animate-float"/>
            <p class="font-semibold">{{ __('Rotate your device to landscape for the best experience.') }}</p>
            <button type="button" class="btn-ghost btn-sm" @click="dismissRotate">{{ __('Continue anyway') }}</button>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-1 border-t border-white/10 bg-card px-2 py-1.5 sm:gap-2 sm:px-3">
        <span class="hidden min-w-0 truncate px-1 text-sm font-semibold sm:block">{{ $title }}</span>
        <span x-cloak x-show="lastScore !== null" class="rounded-md bg-brand/20 px-2 py-0.5 text-xs font-semibold text-brand-2" x-text="scoreLabel"></span>
        <div class="ml-auto flex items-center gap-0.5">
            <button type="button" class="flex size-9 items-center justify-center rounded-lg text-ink-2 hover:bg-card-2 hover:text-ink disabled:opacity-30" @click="restart" :disabled="state === 'idle'" title="{{ __('Restart') }}" aria-label="{{ __('Restart') }}"><x-icon name="restart" class="size-4.5"/></button>
            <button type="button" class="flex size-9 items-center justify-center rounded-lg text-ink-2 hover:bg-card-2 hover:text-ink" @click="toggleMute" title="{{ __('Mute or unmute') }}" aria-label="{{ __('Mute or unmute') }}">
                <span x-show="!muted"><x-icon name="volume" class="size-4.5"/></span><span x-cloak x-show="muted"><x-icon name="mute" class="size-4.5"/></span>
            </button>
            <button type="button" class="flex size-9 items-center justify-center rounded-lg text-ink-2 hover:bg-card-2 hover:text-ink" @click="toggleFullscreen" title="{{ __('Fullscreen') }}" aria-label="{{ __('Fullscreen') }}">
                <span x-show="!(isFullscreen || pseudoFullscreen)"><x-icon name="expand" class="size-4.5"/></span><span x-cloak x-show="isFullscreen || pseudoFullscreen"><x-icon name="shrink" class="size-4.5"/></span>
            </button>
        </div>
    </div>
</div>
