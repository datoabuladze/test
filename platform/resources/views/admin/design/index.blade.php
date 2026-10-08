@extends('layouts.admin')
@section('title', 'Design')
@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        <form method="post" action="{{ route('admin.design.store') }}" x-data="themePreview" class="card space-y-6 p-5">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Theme tokens</h2>
                <p class="text-xs text-ink-3">Colour changes preview live on this page. Saving creates a new version.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($colors as $key => [$label, $var])
                    <label class="block">
                        <span class="label">{{ $label }}</span>
                        <span class="flex items-center gap-2">
                            <input type="color" name="{{ $key }}" value="{{ old($key, $tokens[$key] ?? '#000000') }}" data-css-var="{{ $var }}"
                                   class="h-10 w-14 cursor-pointer rounded-lg border border-line bg-bg-2 p-1">
                            <span class="font-mono text-xs text-ink-3">{{ $tokens[$key] ?? '' }}</span>
                        </span>
                        @error($key)<span class="error block">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['font_display' => 'Display font', 'font_body' => 'Body font'] as $key => $label)
                    <label class="block">
                        <span class="label">{{ $label }}</span>
                        <select name="{{ $key }}" class="input">
                            @foreach ($fonts as $f)<option value="{{ $f }}" @selected(old($key, $tokens[$key] ?? '') === $f)>{{ $f }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
                @foreach (['radius' => 'Corner radius', 'card_style' => 'Card style', 'density' => 'Density', 'default_mode' => 'Default colour mode'] as $key => $label)
                    <label class="block">
                        <span class="label">{{ $label }}</span>
                        <select name="{{ $key }}" class="input">
                            @foreach ($choices[$key] as $opt)<option value="{{ $opt }}" @selected(old($key, $tokens[$key] ?? '') === $opt)>{{ ucfirst($opt) }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
            </div>

            <div class="rounded-[var(--radius-card)] border border-line bg-bg-2 p-5">
                <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-3">Preview</div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-logo class="h-8"/>
                    <span class="text-gradient font-display text-2xl font-black">Play instantly</span>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-primary"><x-icon name="play" class="size-4"/>Play now</button>
                    <button type="button" class="btn-ghost">Secondary</button>
                    <span class="chip chip-active">Active chip</span>
                    <span class="badge bg-brand-2/20 text-brand-2">New</span>
                    <span class="badge bg-brand-3/20 text-brand-3">Hot</span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                <label class="block">
                    <span class="label">Version note</span>
                    <input name="note" value="{{ old('note') }}" maxlength="120" class="input" placeholder="e.g. Autumn colours">
                </label>
                <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="checkbox" name="activate" value="1" checked class="rounded border-line bg-bg-2"> Activate immediately</label>
            </div>
            <button class="btn-primary"><x-icon name="check" class="size-4"/>Save as new version</button>
        </form>

        <section class="card overflow-x-auto">
            <header class="border-b border-line px-5 py-3"><h2 class="font-semibold">Version history</h2></header>
            <table class="table">
                <thead><tr><th>#</th><th>Colours</th><th>Note</th><th>Created</th><th class="text-right">Status</th></tr></thead>
                <tbody>
                @foreach ($versions as $v)
                    <tr>
                        <td class="tabular-nums text-ink-3">{{ $v->id }}</td>
                        <td>
                            <span class="flex gap-1">
                                @foreach (array_keys($colors) as $key)
                                    @php $c = $v->tokens[$key] ?? null; @endphp
                                    @if ($c && preg_match('/^#[0-9a-fA-F]{6}$/', $c))
                                        <svg class="size-5 rounded" viewBox="0 0 10 10" aria-label="{{ $c }}"><rect width="10" height="10" rx="2" fill="{{ $c }}"/></svg>
                                    @endif
                                @endforeach
                            </span>
                        </td>
                        <td>{{ $v->note ?? '—' }}</td>
                        <td class="whitespace-nowrap text-xs text-ink-3">{{ $v->created_at?->format('Y-m-d H:i') }}@if ($v->creator) · {{ $v->creator->nickname }}@endif</td>
                        <td class="text-right">
                            @if ($v->is_active)
                                <span class="badge bg-ok/15 text-ok">active</span>
                            @else
                                <form method="post" action="{{ route('admin.design.activate', $v) }}" x-data="confirmForm" data-confirm="Switch the live site to version #{{ $v->id }}?" @submit="confirmSubmit">
                                    @csrf
                                    <button class="btn-ghost btn-sm"><x-icon name="history" class="size-4"/>Activate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <aside>
        <form method="post" action="{{ route('admin.design.branding') }}" enctype="multipart/form-data" class="card sticky top-20 space-y-5 p-5">
            @csrf
            <h2 class="font-semibold">Branding</h2>
            <div>
                <span class="label">Logo</span>
                @if (! empty($branding['logo']))
                    <div class="mb-2 rounded-xl border border-line bg-bg-2 p-3"><img src="{{ asset('storage/'.$branding['logo']) }}" alt="Current logo" class="h-10 w-auto"></div>
                    <label class="mb-2 flex items-center gap-2 text-xs text-ink-3"><input type="checkbox" name="remove_logo" value="1" class="rounded border-line bg-bg-2"> Remove and use the default logo</label>
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="input">
                <p class="help">PNG, JPG or WebP, max 1&nbsp;MB, up to 2000×1000. Shown at 32px height.</p>
                @error('logo')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="label">Favicon</span>
                @if (! empty($branding['favicon']))
                    <div class="mb-2 rounded-xl border border-line bg-bg-2 p-3"><img src="{{ asset('storage/'.$branding['favicon']) }}" alt="Current favicon" class="size-8"></div>
                    <label class="mb-2 flex items-center gap-2 text-xs text-ink-3"><input type="checkbox" name="remove_favicon" value="1" class="rounded border-line bg-bg-2"> Remove and use the default icon</label>
                @endif
                <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp" class="input">
                <p class="help">Square PNG recommended (e.g. 512×512), max 1&nbsp;MB.</p>
                @error('favicon')<p class="error">{{ $message }}</p>@enderror
            </div>
            <p class="help">SVG uploads are not accepted for security reasons.</p>
            <button class="btn-primary w-full"><x-icon name="upload" class="size-4"/>Save branding</button>
        </form>
    </aside>
</div>
@endsection
