@php $locale = app()->getLocale(); $user = auth()->user(); @endphp
<header class="sticky top-0 z-40 border-b border-line bg-bg/80 backdrop-blur-xl" x-data="disclosure">
    <div class="container-page flex h-16 items-center gap-3">
        <button type="button" class="flex size-10 items-center justify-center rounded-xl text-ink-2 hover:bg-card lg:hidden" @click="toggle" :aria-expanded="open" aria-controls="mobile-nav" aria-label="{{ __('Menu') }}">
            <x-icon name="menu" />
        </button>

        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('platform.brand') }} – {{ __('Home') }}">
            @if (! empty($branding['logo']))
                <img src="{{ asset('storage/'.$branding['logo']) }}" alt="{{ config('platform.brand') }}" class="h-8 w-auto">
            @else
                <x-logo class="h-8"/>
            @endif
        </a>

        {{-- Desktop category mega-menu --}}
        <div class="relative hidden lg:block" x-data="disclosure" @keydown.escape.window="close" @click.outside="close">
            <button type="button" class="btn-ghost h-10 border-transparent bg-transparent" @click="toggle" :aria-expanded="open" aria-haspopup="true">
                <x-icon name="grid" class="size-4"/> {{ __('Games') }} <x-icon name="chevron-down" class="size-3.5"/>
            </button>
            <div x-cloak x-show="open" x-transition.origin.top.left
                 class="absolute left-0 top-12 w-[min(860px,80vw)] rounded-2xl border border-line bg-card p-5 shadow-2xl">
                <div class="grid grid-cols-4 gap-x-6 gap-y-1">
                    @foreach ($navCategories ?? [] as $cat)
                        <div class="py-1">
                            <a href="{{ $cat->url() }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold hover:bg-card-2">
                                <span class="size-2 rounded-full" style="background: {{ $cat->color ?: 'var(--color-brand)' }}"></span>{{ $cat->tr('name') }}
                            </a>
                            @foreach ($cat->children as $child)
                                <a href="{{ $child->url() }}" class="block rounded-md px-2 py-1 pl-6 text-xs text-ink-3 hover:text-ink">{{ $child->tr('name') }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                    <a class="chip" href="{{ route('games.index') }}"><x-icon name="grid" class="size-3.5"/>{{ __('All games') }}</a>
                    <a class="chip" href="{{ route('games.index', ['sort' => 'new']) }}"><x-icon name="sparkle" class="size-3.5"/>{{ __('New games') }}</a>
                    <a class="chip" href="{{ route('games.index', ['sort' => 'popular']) }}"><x-icon name="fire" class="size-3.5"/>{{ __('Most played') }}</a>
                    <a class="chip" href="{{ route('rooms.index') }}"><x-icon name="users" class="size-3.5"/>{{ __('Play with a friend') }}</a>
                    <a class="chip" href="{{ route('leaderboards.index') }}"><x-icon name="trophy" class="size-3.5"/>{{ __('Leaderboards') }}</a>
                </div>
            </div>
        </div>

        {{-- Search --}}
        <form action="{{ route('search') }}" method="get" role="search" class="relative ml-auto w-full max-w-xl md:ml-4"
              x-data="searchBox" data-suggest-url="{{ route('api.search.suggest') }}" @submit="submit" @click.outside="close" @keydown.escape="close">
            <label for="site-search" class="sr-only">{{ __('Search games') }}</label>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-3"/>
            <input id="site-search" name="q" type="search" autocomplete="off" x-model="q" @input="input" @focus="focusOpen"
                   @keydown.down.prevent="down" @keydown.up.prevent="up"
                   placeholder="{{ __('Search games') }}…" class="input h-10 rounded-full pl-10" role="combobox" :aria-expanded="open" aria-controls="search-suggestions">
            <div id="search-suggestions" x-cloak x-show="open" x-transition.opacity
                 class="absolute inset-x-0 top-12 z-50 overflow-hidden rounded-2xl border border-line bg-card shadow-2xl" role="listbox">
                <template x-if="loading">
                    <div class="space-y-2 p-3"><div class="skeleton h-10 rounded-lg"></div><div class="skeleton h-10 rounded-lg"></div></div>
                </template>
                <template x-for="(g, i) in games" :key="g.url">
                    <a :href="g.url" class="flex items-center gap-3 px-3 py-2 hover:bg-card-2" :class="{ 'bg-card-2': isActive(i) }" role="option">
                        <template x-if="g.thumbnail"><img :src="g.thumbnail" alt="" class="h-9 w-12 rounded-md object-cover"></template>
                        <template x-if="!g.thumbnail"><span class="h-9 w-12 rounded-md" :style="{ background: g.color || '#7c5cff' }"></span></template>
                        <span class="truncate text-sm" x-text="g.title"></span>
                    </a>
                </template>
                <template x-if="categories.length">
                    <div class="border-t border-line px-3 py-2 text-xs font-semibold uppercase tracking-wide text-ink-3">{{ __('Categories') }}</div>
                </template>
                <template x-for="(c, i) in categories" :key="c.url">
                    <a :href="c.url" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-card-2" :class="{ 'bg-card-2': isActiveCat(i) }" role="option">
                        <span class="size-1.5 rounded-full bg-brand"></span><span x-text="c.title"></span>
                    </a>
                </template>
                <template x-if="showEmpty">
                    <div class="px-4 py-4 text-sm text-ink-3">{{ __('No games found. Press Enter to search everything.') }}</div>
                </template>
            </div>
        </form>

        <a href="{{ route('games.random') }}" class="hidden size-10 shrink-0 items-center justify-center rounded-xl text-ink-2 hover:bg-card hover:text-ink sm:flex" title="{{ __('Random game') }}" aria-label="{{ __('Random game') }}">
            <x-icon name="dice"/>
        </a>

        {{-- Language --}}
        <div class="relative hidden sm:block" x-data="disclosure" @click.outside="close" @keydown.escape.window="close">
            <button type="button" class="flex h-10 items-center gap-1 rounded-xl px-2.5 text-sm font-semibold uppercase text-ink-2 hover:bg-card hover:text-ink" @click="toggle" :aria-expanded="open" aria-label="{{ __('Language') }}">
                <x-icon name="globe" class="size-4"/>{{ $locale }}
            </button>
            <div x-cloak x-show="open" x-transition class="absolute right-0 top-12 w-44 overflow-hidden rounded-xl border border-line bg-card py-1 shadow-2xl">
                @foreach (config('platform.locales') as $code => $meta)
                    <a href="{{ \App\Support\Seo::localizedCurrentUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}"
                       class="flex items-center justify-between px-3 py-2 text-sm hover:bg-card-2 {{ $code === $locale ? 'text-brand-2' : '' }}">
                        {{ $meta['native'] }} <span class="text-xs uppercase text-ink-3">{{ $code }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <button type="button" x-data="themeToggle" @click="toggle" class="hidden size-10 shrink-0 items-center justify-center rounded-xl text-ink-2 hover:bg-card hover:text-ink sm:flex" aria-label="{{ __('Toggle light/dark theme') }}">
            <span x-show="isDark"><x-icon name="sun"/></span>
            <span x-cloak x-show="!isDark"><x-icon name="moon"/></span>
        </button>

        @auth
            <div class="relative" x-data="disclosure" @click.outside="close" @keydown.escape.window="close">
                <button type="button" class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand to-brand-3 font-bold text-white ring-2 ring-line" @click="toggle" :aria-expanded="open" aria-label="{{ __('Account menu') }}">
                    @if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover">@else{{ $user->initials() }}@endif
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-0 top-12 w-60 overflow-hidden rounded-xl border border-line bg-card py-1 shadow-2xl">
                    <div class="border-b border-line px-4 py-3">
                        <div class="truncate text-sm font-semibold">{{ $user->nickname }}</div>
                        <div class="mt-1 flex items-center gap-2 text-xs text-ink-3">{{ __('Level :n', ['n' => $user->level]) }} · {{ number_format($user->xp) }} XP</div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-gradient-to-r from-brand-2 to-brand" style="width: {{ round($user->levelProgress() * 100) }}%"></div></div>
                    </div>
                    <a href="{{ route('account.dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-card-2"><x-icon name="user" class="size-4"/>{{ __('My profile') }}</a>
                    <a href="{{ route('account.favorites') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-card-2"><x-icon name="heart" class="size-4"/>{{ __('Favorites') }}</a>
                    <a href="{{ route('account.achievements') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-card-2"><x-icon name="medal" class="size-4"/>{{ __('Achievements') }}</a>
                    <a href="{{ route('account.settings') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-card-2"><x-icon name="settings" class="size-4"/>{{ __('Settings') }}</a>
                    @if ($user->isStaff())
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-card-2"><x-icon name="shield" class="size-4"/>{{ __('Admin panel') }}</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}" class="border-t border-line">
                        @csrf
                        <button class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-card-2"><x-icon name="logout" class="size-4"/>{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        @else
            <a href="{{ route('login') }}" class="btn-primary hidden h-10 shrink-0 sm:inline-flex">{{ __('Log in') }}</a>
            <a href="{{ route('login') }}" class="flex size-10 shrink-0 items-center justify-center rounded-xl text-ink-2 hover:bg-card sm:hidden" aria-label="{{ __('Log in') }}"><x-icon name="user"/></a>
        @endauth
    </div>

    {{-- Mobile drawer --}}
    <div id="mobile-nav" x-cloak x-show="open" x-collapse class="border-t border-line lg:hidden">
        <nav class="container-page max-h-[70vh] overflow-y-auto py-4" aria-label="{{ __('Categories') }}">
            <div class="mb-4 grid grid-cols-3 gap-2">
                <a class="chip justify-center" href="{{ route('games.index') }}">{{ __('All games') }}</a>
                <a class="chip justify-center" href="{{ route('games.random') }}"><x-icon name="dice" class="size-3.5"/>{{ __('Random') }}</a>
                <a class="chip justify-center" href="{{ route('rooms.index') }}"><x-icon name="users" class="size-3.5"/>2P</a>
            </div>
            <div class="grid grid-cols-2 gap-1">
                @foreach ($navCategories ?? [] as $cat)
                    <a href="{{ $cat->url() }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-card">
                        <span class="size-2 rounded-full" style="background: {{ $cat->color ?: 'var(--color-brand)' }}"></span>{{ $cat->tr('name') }}
                    </a>
                @endforeach
            </div>
            <div class="mt-4 flex items-center gap-2 border-t border-line pt-4">
                @foreach (config('platform.locales') as $code => $meta)
                    <a href="{{ \App\Support\Seo::localizedCurrentUrl($code) }}" class="chip {{ $code === $locale ? 'chip-active' : '' }}">{{ $meta['native'] }}</a>
                @endforeach
                <button type="button" x-data="themeToggle" @click="toggle" class="chip ml-auto" aria-label="{{ __('Toggle light/dark theme') }}">
                    <span x-show="isDark"><x-icon name="sun" class="size-3.5"/></span><span x-cloak x-show="!isDark"><x-icon name="moon" class="size-3.5"/></span>
                </button>
            </div>
        </nav>
    </div>
</header>
