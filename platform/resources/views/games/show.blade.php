@extends('layouts.app')

@push('head')
    @vite('resources/js/player.js')
@endpush

@php
    $title = $game->tr('title');
    $primary = $game->primaryCategory();
    $ratio = ($game->width && $game->height) ? $game->width.' / '.$game->height : '16 / 9';
    $loginUrl = auth()->check() ? null : route('login');
    $inputs = $game->input_types ?? [];
@endphp

@section('content')
<div class="container-page pt-4">
    <x-breadcrumbs :items="array_values(array_filter([
        [__('Home'), route('home')],
        $primary ? [$primary->tr('name'), $primary->url()] : null,
        [$title, null],
    ]))" class="mb-3"/>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0">
            {{-- Player --}}
            <div x-data="gamePlayer"
                 data-frame-url="{{ $frameUrl }}"
                 data-engine="{{ $game->engine->value }}"
                 data-game-id="{{ $game->id }}"
                 data-play-url="{{ route('api.plays.start', $game) }}"
                 data-score-mode="{{ $game->score_mode }}"
                 data-score-session-url="{{ auth()->check() && $game->score_mode !== 'none' ? route('api.scores.session', $game) : '' }}"
                 data-score-url="{{ auth()->check() && $game->score_mode !== 'none' ? route('api.scores.store', $game) : '' }}"
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

            {{-- Meta & actions --}}
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold sm:text-2xl">{{ $title }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-3">
                        @if ($game->developer)<span>{{ __('by :dev', ['dev' => $game->developer]) }}</span>@endif
                        <span class="inline-flex items-center gap-1"><x-icon name="play" class="size-3"/>{{ trans_choice(':count play|:count plays', $game->play_count, ['count' => \Illuminate\Support\Number::format($game->play_count)]) }}</span>
                        <span>{{ $game->engine->label() }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div x-data="ratingWidget" data-url="{{ route('api.ratings.store', $game) }}" data-value="{{ $userRating ?? 0 }}" data-avg="{{ $game->rating_avg }}" data-count="{{ $game->rating_count }}" @if($loginUrl) data-login="{{ $loginUrl }}" @endif
                         class="flex items-center gap-2 rounded-xl border border-line bg-card px-3 py-1.5" @mouseleave="leave">
                        <div class="flex" role="radiogroup" aria-label="{{ __('Rate this game') }}">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" @click="rate({{ $i }})" @mouseenter="enter({{ $i }})" class="p-0.5" role="radio" aria-label="{{ trans_choice(':count star|:count stars', $i) }}">
                                    <x-icon name="star" class="size-4.5 transition" ::class="shown({{ $i }}) ? 'fill-amber-300 text-amber-300' : 'text-ink-3'"/>
                                </button>
                            @endfor
                        </div>
                        <span class="text-xs text-ink-2"><span x-text="avgLabel"></span> (<span x-text="count"></span>)</span>
                    </div>
                    <button type="button" x-data="favoriteButton" data-url="{{ route('api.favorites.toggle', $game) }}" data-on="{{ $isFavorite ? '1' : '0' }}" @if($loginUrl) data-login="{{ $loginUrl }}" @endif
                            @click="toggle" :disabled="busy" class="btn-ghost" :class="{ '!border-brand-3 !text-brand-3': on }" :aria-pressed="on">
                        <x-icon name="heart" class="size-4" ::class="on ? 'fill-current' : ''"/><span class="hidden sm:inline">{{ __('Favorite') }}</span>
                    </button>
                    <button type="button" x-data="shareButton" @click="share" data-title="{{ $title }}" data-url="{{ $game->url() }}" data-copied="{{ __('Link copied!') }}" class="btn-ghost">
                        <x-icon name="share" class="size-4"/><span class="hidden sm:inline">{{ __('Share') }}</span>
                    </button>
                    @include('games.partials.report')
                </div>
            </div>

            <x-ad-slot placement="game_below" class="mt-6"/>

            {{-- Details --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div class="card p-5 sm:p-6">
                    @if ($game->tr('description'))
                        <h2 class="text-lg font-bold">{{ __('About this game') }}</h2>
                        <div class="prose-content mt-3 text-sm">{!! \Illuminate\Support\Str::markdown($game->tr('description'), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                    @if ($game->tr('instructions'))
                        <h2 class="mt-6 text-lg font-bold">{{ __('How to play') }}</h2>
                        <div class="prose-content mt-3 text-sm">{!! \Illuminate\Support\Str::markdown($game->tr('instructions'), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                    @if ($game->tr('controls'))
                        <h2 class="mt-6 text-lg font-bold">{{ __('Controls') }}</h2>
                        <div class="prose-content mt-3 text-sm">{!! \Illuminate\Support\Str::markdown($game->tr('controls'), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                    @if ($game->engine === \App\Enums\GameEngine::Ruffle)
                        <div class="mt-6 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-ink-2">
                            <strong class="text-ink">{{ __('Flash classic') }}</strong> –
                            {{ __('This game runs through Ruffle, an open-source Flash emulator. No Flash plugin is needed.') }}
                            @if ($game->flash_compatibility === \App\Enums\FlashCompatibility::Partial)
                                {{ __('Some features may not work perfectly.') }}
                            @endif
                        </div>
                    @endif
                </div>
                <aside class="space-y-4">
                    <div class="card p-5">
                        <h2 class="text-sm font-semibold">{{ __('Game info') }}</h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ __('Devices') }}</dt>
                                <dd class="flex gap-1.5 text-ink-2">
                                    @foreach ($game->devices ?: ['desktop', 'tablet', 'mobile'] as $d)
                                        <span title="{{ __(ucfirst($d)) }}">{{ __(ucfirst($d)) }}</span>@if(! $loop->last)<span class="text-ink-3">·</span>@endif
                                    @endforeach
                                </dd></div>
                            @if ($inputs)
                                <div class="flex justify-between gap-3"><dt class="text-ink-3">{{ __('Input') }}</dt>
                                    <dd class="flex gap-1.5 text-ink-2">
                                        @foreach ($inputs as $in)<x-icon :name="['keyboard' => 'keyboard', 'mouse' => 'mouse', 'touch' => 'touch', 'gamepad' => 'gamepad'][$in] ?? 'dots'" class="size-4" title="{{ __(ucfirst($in)) }}"/>@endforeach
                                    </dd></div>
                            @endif
                            @if ($game->difficulty)<div class="flex justify-between"><dt class="text-ink-3">{{ __('Difficulty') }}</dt><dd>{{ __(ucfirst($game->difficulty)) }}</dd></div>@endif
                            @if ($game->session_length)<div class="flex justify-between"><dt class="text-ink-3">{{ __('Session') }}</dt><dd>{{ __(ucfirst($game->session_length)) }}</dd></div>@endif
                            @if ($game->min_age)<div class="flex justify-between"><dt class="text-ink-3">{{ __('Age') }}</dt><dd>{{ $game->min_age }}+</dd></div>@endif
                            @if ($game->published_at)<div class="flex justify-between"><dt class="text-ink-3">{{ __('Added') }}</dt><dd>{{ $game->published_at->translatedFormat('j M Y') }}</dd></div>@endif
                        </dl>
                        @if ($game->score_mode !== 'none')
                            <a href="{{ route('leaderboards.show', $game) }}" class="btn-ghost mt-4 w-full"><x-icon name="trophy" class="size-4"/>{{ __('Leaderboard') }}</a>
                        @endif
                    </div>
                    @if ($game->categories->isNotEmpty() || $game->tags->isNotEmpty())
                        <div class="card p-5">
                            <h2 class="text-sm font-semibold">{{ __('Categories & tags') }}</h2>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($game->categories as $cat)<a class="chip" href="{{ $cat->url() }}">{{ $cat->tr('name') }}</a>@endforeach
                                @foreach ($game->tags as $tag)<a class="chip" href="{{ route('tags.show', $tag) }}">#{{ $tag->tr('name') }}</a>@endforeach
                            </div>
                        </div>
                    @endif
                    <div class="card p-5 text-xs text-ink-3">
                        <h2 class="mb-2 text-sm font-semibold text-ink">{{ __('Credits & license') }}</h2>
                        @if ($game->is_original)
                            <p>{{ __('An original game made for :brand.', ['brand' => config('platform.brand')]) }}</p>
                        @else
                            @if ($game->developer)<p>{{ __('Developer') }}: @if($game->developer_url)<a class="underline" href="{{ $game->developer_url }}" rel="noopener nofollow" target="_blank">{{ $game->developer }}</a>@else{{ $game->developer }}@endif</p>@endif
                            @if ($game->provider)<p>{{ __('Provider') }}: {{ $game->provider->name }}</p>@endif
                        @endif
                        @if ($game->license_type)<p class="mt-1">{{ __('License') }}: @if($game->license_url)<a class="underline" href="{{ $game->license_url }}" rel="noopener nofollow" target="_blank">{{ $game->license_type }}</a>@else{{ $game->license_type }}@endif</p>@endif
                        @if ($game->attribution_text)<p class="mt-1">{{ $game->attribution_text }}</p>@endif
                    </div>
                </aside>
            </div>
        </div>

        {{-- Similar games --}}
        <aside class="min-w-0">
            <h2 class="mb-3 text-lg font-bold">{{ __('You may also like') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-2">
                @foreach ($similar->take(12) as $g)
                    <x-game-card :game="$g" size="grid"/>
                @endforeach
            </div>
            <x-ad-slot placement="sidebar" class="mt-6"/>
        </aside>
    </div>
</div>
@endsection
