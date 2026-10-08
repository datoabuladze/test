<footer class="border-t border-line bg-bg-2/60">
    <div class="container-page grid gap-10 py-12 md:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <x-logo class="h-8"/>
            <p class="mt-3 max-w-sm text-sm text-ink-2">{{ __('Free browser games you can play instantly on desktop, tablet and phone. No downloads, no installs.') }}</p>
            <p class="mt-4 text-xs text-ink-3">{{ __('Every game here is original or published with documented permission from its creator.') }}</p>
        </div>
        <div>
            <h2 class="mb-3 text-sm font-semibold">{{ __('Play') }}</h2>
            <ul class="space-y-2 text-sm text-ink-2">
                @foreach (($navCategories ?? collect())->take(6) as $cat)
                    <li><a class="hover:text-ink" href="{{ $cat->url() }}">{{ $cat->tr('name') }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="mb-3 text-sm font-semibold">{{ __('Discover') }}</h2>
            <ul class="space-y-2 text-sm text-ink-2">
                <li><a class="hover:text-ink" href="{{ route('games.index', ['sort' => 'new']) }}">{{ __('New games') }}</a></li>
                <li><a class="hover:text-ink" href="{{ route('games.index', ['sort' => 'popular']) }}">{{ __('Most played') }}</a></li>
                <li><a class="hover:text-ink" href="{{ route('leaderboards.index') }}">{{ __('Leaderboards') }}</a></li>
                <li><a class="hover:text-ink" href="{{ route('rooms.index') }}">{{ __('Play with a friend') }}</a></li>
                <li><a class="hover:text-ink" href="{{ route('blog.index') }}">{{ __('News') }}</a></li>
                @foreach ($menus['footer_main'] ?? [] as $item)
                    <li><a class="hover:text-ink" href="{{ $item->href() }}" @if($item->isExternal()) rel="noopener" target="_blank" @endif>{{ $item->tr('label') }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="mb-3 text-sm font-semibold">{{ __('Legal') }}</h2>
            <ul class="space-y-2 text-sm text-ink-2">
                @forelse ($menus['footer_legal'] ?? [] as $item)
                    <li><a class="hover:text-ink" href="{{ $item->href() }}">{{ $item->tr('label') }}</a></li>
                @empty
                    <li><a class="hover:text-ink" href="{{ route('pages.show', 'privacy') }}">{{ __('Privacy policy') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('pages.show', 'terms') }}">{{ __('Terms of use') }}</a></li>
                @endforelse
                @if (config('platform.analytics.ga4_measurement_id') || config('platform.ads.adsense_client'))
                    <li x-data="consentLink"><button type="button" class="hover:text-ink" @click="reopen">{{ __('Cookie settings') }}</button></li>
                @endif
            </ul>
        </div>
    </div>
    <div class="border-t border-line">
        <div class="container-page flex flex-col items-center justify-between gap-2 py-5 text-xs text-ink-3 sm:flex-row">
            <span>© {{ date('Y') }} {{ config('platform.brand') }}. {{ __('All game titles and trademarks belong to their respective owners.') }}</span>
            <span>{{ __('No gambling. No real-money games.') }}</span>
        </div>
    </div>
</footer>
