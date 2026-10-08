@extends('layouts.admin')
@section('title', 'Homepage')
@section('content')
@php $locales = config('platform.locales'); @endphp
<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-ink-3">Drag sections to reorder them (or use the arrow buttons). The order is saved automatically.</p>
            <a href="{{ route('home', ['locale' => 'en']) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-icon name="external" class="size-4"/>View homepage</a>
        </div>

        <ul x-data="sortableList" data-url="{{ route('admin.homepage.reorder') }}" class="space-y-2">
            @forelse ($sections as $section)
                @php $type = $section->type->value; @endphp
                <li data-id="{{ $section->id }}" draggable="true" x-data="disclosure" class="card p-3 {{ $section->is_enabled ? '' : 'opacity-60' }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="cursor-grab text-ink-3" title="Drag to reorder" aria-hidden="true"><x-icon name="dots" class="size-5"/></span>
                        <div class="flex flex-col">
                            <button type="button" data-move="up" class="text-ink-3 hover:text-ink" aria-label="Move up"><x-icon name="chevron-down" class="size-4 rotate-180"/></button>
                            <button type="button" data-move="down" class="text-ink-3 hover:text-ink" aria-label="Move down"><x-icon name="chevron-down" class="size-4"/></button>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ $section->tr('title', 'en') ?: $section->type->label() }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-ink-3">
                                <span class="chip !py-0.5">{{ $section->type->label() }}</span>
                                @if ($section->cfg('limit'))<span>limit {{ $section->cfg('limit') }}</span>@endif
                                @if ($section->cfg('category'))<span class="font-mono">category: {{ $section->cfg('category') }}</span>@endif
                                @if ($section->cfg('placement'))<span class="font-mono">placement: {{ $section->cfg('placement') }}</span>@endif
                            </div>
                        </div>
                        <form method="post" action="{{ route('admin.homepage.update', $section) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="toggle" value="1">
                            <button class="badge {{ $section->is_enabled ? 'bg-ok/15 text-ok' : 'bg-card-2 text-ink-3' }}" title="Click to {{ $section->is_enabled ? 'hide' : 'show' }}">{{ $section->is_enabled ? 'enabled' : 'hidden' }}</button>
                        </form>
                        <button type="button" class="btn-ghost btn-sm" @click="toggle" :aria-expanded="open"><x-icon name="edit" class="size-4"/>Edit</button>
                        <form method="post" action="{{ route('admin.homepage.destroy', $section) }}" x-data="confirmForm" data-confirm="Remove this section?" @submit="confirmSubmit">
                            @csrf @method('DELETE')
                            <button class="btn-danger btn-sm" title="Remove"><x-icon name="trash" class="size-4"/></button>
                        </form>
                    </div>

                    <form method="post" action="{{ route('admin.homepage.update', $section) }}" x-show="open" x-cloak class="mt-3 space-y-3 rounded-xl border border-line bg-bg-2/50 p-4">
                        @csrf @method('PUT')
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($locales as $code => $meta)
                                <label class="block">
                                    <span class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-ink-3">Title · {{ $code }}</span>
                                    <input name="title[{{ $code }}]" value="{{ $section->title[$code] ?? '' }}" class="input" lang="{{ $code }}" maxlength="120">
                                </label>
                            @endforeach
                        </div>
                        @if (in_array($type, $limited, true))
                            <label class="block max-w-40"><span class="label">Number of games</span>
                                <input type="number" name="limit" value="{{ $section->cfg('limit') }}" min="1" max="48" class="input" placeholder="default">
                            </label>
                        @endif
                        @if ($type === 'category')
                            <label class="block"><span class="label">Category</span>
                                <select name="category" class="input" required>
                                    @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($section->cfg('category') === $c->slug)>{{ $c->tr('name', 'en') }} ({{ $c->slug }})</option>@endforeach
                                </select>
                            </label>
                        @elseif ($type === 'ad')
                            <label class="block"><span class="label">Ad placement</span>
                                <select name="placement" class="input" required>
                                    @foreach ($placements as $p)<option value="{{ $p->key }}" @selected($section->cfg('placement') === $p->key)>{{ $p->name }} ({{ $p->key }}){{ $p->is_enabled ? '' : ' – disabled' }}</option>@endforeach
                                </select>
                            </label>
                        @elseif ($type === 'seo_text')
                            <div class="space-y-2">
                                <span class="label !mb-0">Body (Markdown)</span>
                                @foreach ($locales as $code => $meta)
                                    <label class="block">
                                        <span class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-ink-3">{{ $code }} · {{ $meta['name'] }}</span>
                                        <textarea name="body[{{ $code }}]" rows="5" class="input font-mono text-xs" lang="{{ $code }}">{{ $section->cfg('body.'.$code) }}</textarea>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($section->is_enabled) class="rounded border-line bg-bg-2"> Enabled</label>
                            <button class="btn-primary btn-sm"><x-icon name="check" class="size-4"/>Save section</button>
                        </div>
                    </form>
                </li>
            @empty
                <li class="card p-8 text-center text-sm text-ink-3">No sections yet. Add one on the right.</li>
            @endforelse
        </ul>
    </section>

    <aside>
        <form method="post" action="{{ route('admin.homepage.store') }}" class="card sticky top-20 space-y-4 p-5">
            @csrf
            <h2 class="font-semibold">Add section</h2>
            <div>
                <label class="label" for="f-type">Type</label>
                <select id="f-type" name="type" class="input" required>
                    @foreach ($types as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach
                </select>
            </div>
            <x-admin.translatable name="title" label="Title" help="Optional for some types (category rails fall back to the category name)."/>
            <div>
                <label class="label" for="f-limit">Number of games</label>
                <input id="f-limit" type="number" name="limit" value="{{ old('limit') }}" min="1" max="48" class="input" placeholder="default">
                <p class="help">Used by game rails and the hero carousel.</p>
            </div>
            <div>
                <label class="label" for="f-category">Category <span class="text-ink-3">(Category type only)</span></label>
                <select id="f-category" name="category" class="input">
                    <option value="">—</option>
                    @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(old('category') === $c->slug)>{{ $c->tr('name', 'en') }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="f-placement">Ad placement <span class="text-ink-3">(Ad type only)</span></label>
                <select id="f-placement" name="placement" class="input">
                    <option value="">—</option>
                    @foreach ($placements as $p)<option value="{{ $p->key }}" @selected(old('placement') === $p->key)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <p class="help">SEO text body can be written after adding the section (Edit).</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" checked class="rounded border-line bg-bg-2"> Enabled</label>
            <button class="btn-primary w-full"><x-icon name="plus" class="size-4"/>Add section</button>
        </form>
    </aside>
</div>
@endsection
