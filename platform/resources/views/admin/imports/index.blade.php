@extends('layouts.admin')
@section('title', 'Imports')

@section('content')
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>#</th><th>Source</th><th>Status</th><th class="text-right">Rows</th><th class="text-right">Valid</th><th class="text-right">Dupes</th><th class="text-right">Invalid</th><th class="text-right">Imported</th><th>By</th><th>When</th></tr></thead>
            <tbody>
            @forelse ($batches as $b)
                <tr>
                    <td><a href="{{ route('admin.imports.show', $b) }}" class="font-medium text-brand-2">#{{ $b->id }}</a></td>
                    <td class="text-xs">{{ strtoupper($b->source) }} {{ $b->provider?->name }}</td>
                    <td><x-admin.status :value="$b->status"/></td>
                    <td class="text-right tabular-nums">{{ $b->total }}</td>
                    <td class="text-right tabular-nums">{{ $b->valid }}</td>
                    <td class="text-right tabular-nums">{{ $b->duplicates }}</td>
                    <td class="text-right tabular-nums">{{ $b->failed }}</td>
                    <td class="text-right tabular-nums">{{ $b->imported }}</td>
                    <td class="text-xs">{{ $b->creator?->nickname }}</td>
                    <td class="text-xs text-ink-3">{{ $b->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="py-6 text-center text-ink-3">No imports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $batches->links() }}</div>
    </div>

    <div class="space-y-4">
        <form method="post" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="card space-y-3 p-4">
            @csrf
            <h2 class="font-bold">New import</h2>
            <div><label class="label" for="source">Source</label>
                <select id="source" name="source" class="input">
                    <option value="csv">CSV file</option><option value="json">JSON file</option><option value="provider">Provider feed</option>
                </select></div>
            <div><label class="label" for="provider_id">Provider</label>
                <select id="provider_id" name="provider_id" class="input"><option value="">None</option>
                    @foreach ($providers as $p)<option value="{{ $p->id }}" @disabled(! $p->is_active)>{{ $p->name }} ({{ $p->adapter }})</option>@endforeach
                </select>
                <p class="help">Needed for embedded games: embed hosts are checked against this provider's allow-list, and its licensing defaults fill blank fields.</p></div>
            <div><label class="label" for="file">File (CSV or JSON, max 20 MB)</label><input id="file" type="file" name="file" accept=".csv,.json,.txt" class="input"></div>
            <button class="btn-primary w-full"><x-icon name="upload" class="size-4"/>Upload and preview</button>
            <p class="text-xs text-ink-3">Nothing is added to the catalog until you review the preview and run the import. Imported games are drafts with unverified rights.</p>
        </form>
        <section class="card p-4 text-sm">
            <h2 class="mb-2 font-bold">Columns</h2>
            <p class="mb-2 text-xs text-ink-3">Header names (CSV) or keys (JSON). Add <code>_ka</code>, <code>_tr</code>, <code>_ru</code> to text fields for translations. Lists use <code>|</code>. See docs/GAME_IMPORT_GUIDE.md.</p>
            <p class="font-mono text-[11px] leading-relaxed text-ink-2">{{ implode(', ', $fields) }}</p>
        </section>
    </div>
</div>
@endsection
