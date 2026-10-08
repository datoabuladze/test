@extends('layouts.admin')
@section('title', 'Translations')
@section('content')
<div class="mb-4 grid gap-4 sm:grid-cols-4">
    <x-admin.stat label="UI strings" :value="number_format($total)" icon="globe"/>
    @foreach ($locales as $l)
        <x-admin.stat :label="($localeMeta[$l]['name'] ?? $l).' – missing'" :value="number_format($missingCounts[$l] ?? 0)" icon="alert"/>
    @endforeach
</div>

<form method="get" class="mb-4 flex flex-wrap items-center gap-2">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search keys or translations" class="input max-w-sm">
    <label class="chip {{ $onlyMissing ? 'chip-active' : '' }} cursor-pointer"><input type="checkbox" name="missing" value="1" @checked($onlyMissing) class="rounded border-line bg-bg-2"> Only missing</label>
    <button class="btn-ghost"><x-icon name="search" class="size-4"/>Search</button>
    @if ($q !== '' || $onlyMissing)<a href="{{ route('admin.translations.index') }}" class="text-sm text-ink-3 hover:text-ink">Reset</a>@endif
    <span class="ml-auto text-xs text-ink-3">{{ number_format($paginator->total()) }} matching</span>
</form>

@if ($total === 0)
    <div class="card p-8 text-center text-sm text-ink-3">No UI strings found in <code>lang/*.json</code> yet.</div>
@else
<form method="post" action="{{ route('admin.translations.update') }}">
    @csrf
    <div class="card overflow-x-auto">
        <table class="table min-w-[900px]">
            <thead>
                <tr>
                    <th class="w-1/4">English (key)</th>
                    @foreach ($locales as $l)<th>{{ $localeMeta[$l]['name'] ?? $l }} <span class="font-mono">{{ $l }}</span></th>@endforeach
                </tr>
            </thead>
            <tbody>
            @forelse ($paginator as $key)
                @php $field = \App\Http\Controllers\Admin\TranslationController::encodeKey($key); @endphp
                <tr>
                    <td class="align-top text-xs">
                        <div class="whitespace-pre-wrap break-words">{{ $files['en'][$key] ?? $key }}</div>
                        @if (isset($files['en'][$key]) && $files['en'][$key] !== $key)<div class="mt-1 font-mono text-[10px] text-ink-3 break-all">key: {{ $key }}</div>@endif
                    </td>
                    @foreach ($locales as $l)
                        @php $v = $files[$l][$key] ?? ''; @endphp
                        <td class="align-top">
                            <textarea name="translations[{{ $l }}][{{ $field }}]" rows="{{ mb_strlen($key) > 80 ? 3 : 1 }}" lang="{{ $l }}"
                                      class="input !py-1.5 text-xs {{ $v === '' ? 'border-warn/60' : '' }}" aria-label="{{ $l }}: {{ \Illuminate\Support\Str::limit($key, 60) }}">{{ $v }}</textarea>
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($locales) + 1 }}" class="py-8 text-center text-ink-3">No strings match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="help !mt-0">Saves only the strings on this page. Empty fields fall back to English. Placeholders like <code>:name</code> must be kept.</p>
        <button class="btn-primary"><x-icon name="check" class="size-4"/>Save this page</button>
    </div>
</form>
<div class="mt-4">{{ $paginator->links() }}</div>
@endif
@endsection
