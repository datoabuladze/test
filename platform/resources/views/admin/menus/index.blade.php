@extends('layouts.admin')
@section('title', 'Menus')
@section('content')
@php $locales = config('platform.locales'); @endphp
<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        @foreach ($menus as $key => $name)
            <section class="card">
                <header class="flex items-center justify-between border-b border-line px-5 py-3">
                    <h2 class="font-semibold">{{ $name }}</h2>
                    <span class="text-xs text-ink-3 font-mono">{{ $key }}</span>
                </header>
                <ul class="divide-y divide-line">
                    @forelse ($items->get($key, collect()) as $item)
                        <li x-data="disclosure" class="px-5 py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="w-8 text-xs tabular-nums text-ink-3">#{{ $item->sort_order }}</span>
                                <span class="font-medium">{{ $item->tr('label', 'en') }}</span>
                                <span class="truncate font-mono text-xs text-ink-3">{{ $item->url }}</span>
                                @if ($item->isExternal())<x-icon name="external" class="size-3.5 text-ink-3"/>@endif
                                @unless ($item->is_enabled)<span class="badge bg-card-2 text-ink-3">hidden</span>@endunless
                                <div class="ml-auto flex items-center gap-2">
                                    <button type="button" class="btn-ghost btn-sm" @click="toggle" :aria-expanded="open"><x-icon name="edit" class="size-4"/>Edit</button>
                                    <form method="post" action="{{ route('admin.menus.destroy', $item) }}" x-data="confirmForm" data-confirm="Remove this menu item?" @submit="confirmSubmit">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger btn-sm" title="Remove"><x-icon name="trash" class="size-4"/></button>
                                    </form>
                                </div>
                            </div>
                            <form method="post" action="{{ route('admin.menus.update', $item) }}" x-show="open" x-cloak class="mt-3 space-y-3 rounded-xl border border-line bg-bg-2/50 p-4">
                                @csrf @method('PUT')
                                <input type="hidden" name="menu" value="{{ $item->menu }}">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($locales as $code => $meta)
                                        <label class="block">
                                            <span class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-ink-3">Label · {{ $code }}</span>
                                            <input name="label[{{ $code }}]" value="{{ $item->label[$code] ?? '' }}" class="input" lang="{{ $code }}" maxlength="80" @if($code === 'en') required @endif>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
                                    <label class="block"><span class="label">URL</span><input name="url" value="{{ $item->url }}" class="input font-mono" required maxlength="512"></label>
                                    <label class="block"><span class="label">Order</span><input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" class="input"></label>
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($item->is_enabled) class="rounded border-line bg-bg-2"> Visible</label>
                                    <button class="btn-primary btn-sm"><x-icon name="check" class="size-4"/>Save</button>
                                </div>
                            </form>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-ink-3">No items in this menu.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>

    <aside>
        <form method="post" action="{{ route('admin.menus.store') }}" class="card sticky top-20 space-y-4 p-5">
            @csrf
            <h2 class="font-semibold">Add menu item</h2>
            <div>
                <label class="label" for="f-menu">Menu</label>
                <select id="f-menu" name="menu" class="input">
                    @foreach ($menus as $key => $name)<option value="{{ $key }}" @selected(old('menu') === $key)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <x-admin.translatable name="label" label="Label" required/>
            <div>
                <label class="label" for="f-url">URL</label>
                <input id="f-url" name="url" value="{{ old('url') }}" class="input font-mono" required maxlength="512" placeholder="p/about or https://…">
                <p class="help">Relative paths get the visitor's language prefix (e.g. <code>p/about</code> → /en/p/about).</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="label">Order</span><input type="number" name="sort_order" value="{{ old('sort_order') }}" min="0" class="input" placeholder="last"></label>
                <label class="mt-7 flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" checked class="rounded border-line bg-bg-2"> Visible</label>
            </div>
            <button class="btn-primary w-full"><x-icon name="plus" class="size-4"/>Add item</button>
        </form>
    </aside>
</div>
@endsection
