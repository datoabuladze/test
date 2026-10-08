<section class="py-6">
    <h2 class="mb-4 text-lg font-bold sm:text-xl">{{ $title }}</h2>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($categories as $cat)
            <a href="{{ $cat->url() }}" class="group relative overflow-hidden rounded-2xl border border-line bg-card p-4 transition hover:-translate-y-0.5 hover:border-transparent hover:shadow-glow">
                <div class="absolute -right-6 -bottom-6 size-24 rounded-full opacity-30 blur-2xl transition group-hover:opacity-60" style="background: {{ $cat->color ?: '#7c5cff' }}"></div>
                <div class="relative">
                    <div class="text-sm font-bold">{{ $cat->tr('name') }}</div>
                    <div class="mt-1 text-xs text-ink-3">{{ trans_choice(':count game|:count games', $cat->games_count) }}</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
