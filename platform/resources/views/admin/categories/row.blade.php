<div class="flex items-center gap-3">
    <span class="cursor-grab text-ink-3" aria-hidden="true"><x-icon name="dots" class="size-4"/></span>
    <span class="size-3 shrink-0 rounded-full" style="background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c->color) ? $c->color : '#7c5cff' }}"></span>
    <div class="min-w-0 flex-1">
        <a href="{{ route('admin.categories.edit', $c) }}" class="font-medium hover:text-brand-2">{{ $c->tr('name', 'en') }}</a>
        <span class="text-xs text-ink-3">/{{ $c->slug }} · {{ $c->games_count }} games</span>
        @unless ($c->is_active)<x-admin.status value="archived"/>@endunless
        @if ($c->show_on_home)<span class="badge bg-brand/15 text-brand-2">home</span>@endif
        @unless ($c->show_in_menu)<span class="badge bg-card-2 text-ink-3">hidden in menu</span>@endunless
        @php $missing = collect(array_keys(config('platform.locales')))->reject(fn ($l) => $c->hasTranslation('name', $l)); @endphp
        @if ($missing->isNotEmpty())<span class="badge bg-warn/15 text-warn">missing {{ $missing->join(', ') }}</span>@endif
    </div>
    <div class="flex shrink-0 items-center gap-1">
        <button type="button" class="btn-ghost btn-sm" data-move="up" aria-label="Move up"><x-icon name="chevron-left" class="size-4 rotate-90"/></button>
        <button type="button" class="btn-ghost btn-sm" data-move="down" aria-label="Move down"><x-icon name="chevron-right" class="size-4 rotate-90"/></button>
        <a href="{{ $c->url('en') }}" class="btn-ghost btn-sm" target="_blank" rel="noopener" aria-label="View"><x-icon name="external" class="size-4"/></a>
        <a href="{{ route('admin.categories.edit', $c) }}" class="btn-ghost btn-sm" aria-label="Edit"><x-icon name="edit" class="size-4"/></a>
    </div>
</div>
