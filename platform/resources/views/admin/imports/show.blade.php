@extends('layouts.admin')
@section('title', 'Import #'.$batch->id)

@section('content')
<div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
    <x-admin.stat label="Status" :value="ucfirst($batch->status)" icon="upload"/>
    <x-admin.stat label="Rows" :value="$batch->total" icon="file"/>
    <x-admin.stat label="Valid (ready)" :value="$counts['valid'] ?? 0" icon="check"/>
    <x-admin.stat label="Duplicates" :value="$counts['duplicate'] ?? 0" icon="refresh"/>
    <x-admin.stat label="Imported" :value="$counts['imported'] ?? 0" icon="gamepad"/>
</div>
@if ($batch->error)<div class="mb-4 rounded-xl border border-bad/30 bg-bad/10 px-4 py-3 text-sm text-bad">{{ $batch->error }}</div>@endif
<p class="mb-4 text-sm text-ink-3">Source: {{ strtoupper($batch->source) }} {{ $batch->provider?->name }} · started by {{ $batch->creator?->nickname ?? 'unknown' }} {{ $batch->created_at->diffForHumans() }}</p>

<div class="mb-3 flex flex-wrap gap-2 text-sm">
    @foreach (['' => 'All', 'valid' => 'Valid', 'invalid' => 'Invalid', 'duplicate' => 'Duplicates', 'imported' => 'Imported', 'failed' => 'Failed'] as $k => $l)
        <a href="{{ route('admin.imports.show', [$batch, 'status' => $k ?: null]) }}" class="chip {{ request('status', '') === $k ? 'chip-active' : '' }}">{{ $l }} @if ($k)({{ $counts[$k] ?? 0 }})@endif</a>
    @endforeach
</div>

<form method="post" action="{{ route('admin.imports.run', $batch) }}" x-data="bulkTable" class="card overflow-hidden">
    @csrf
    <div class="flex flex-wrap items-center gap-3 border-b border-line p-3 text-sm">
        <label class="flex items-start gap-2 text-xs text-ink-2"><input type="checkbox" name="confirm" value="1" class="mt-0.5">I confirm the provider agreement or license allows listing these games.</label>
        <span class="text-ink-3" x-text="selectionLabel"></span>
        <button name="mode" value="selected" class="btn-ghost btn-sm" :disabled="!hasSelection">Import selected</button>
        <button name="mode" value="all_valid" class="btn-primary btn-sm" @disabled(! ($counts['valid'] ?? 0))>Import all valid ({{ $counts['valid'] ?? 0 }})</button>
        <button name="mode" value="repreview" class="btn-ghost btn-sm ml-auto"><x-icon name="refresh" class="size-4"/>Re-run preview</button>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th class="w-8"><input type="checkbox" @change="toggleAll" aria-label="Select all"></th><th>Row</th><th>Title</th><th>Engine / URL</th><th>License</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                @php $n = $item->normalized ?? []; @endphp
                <tr>
                    <td>@if ($item->status === 'valid')<input type="checkbox" name="ids[]" value="{{ $item->id }}">@endif</td>
                    <td class="tabular-nums text-ink-3">{{ $item->row_number }}</td>
                    <td><span class="font-medium">{{ $n['title']['en'] ?? '—' }}</span>
                        @if ($item->game)<div class="text-xs"><a href="{{ route('admin.games.edit', $item->game) }}" class="text-brand-2">Game #{{ $item->game_id }}</a></div>@endif
                        <div class="text-xs text-ink-3">{{ implode(', ', $n['categories'] ?? []) }}</div></td>
                    <td class="max-w-xs truncate text-xs">{{ $n['engine'] ?? '' }} · {{ $n['embed_url'] ?? '' }}</td>
                    <td class="text-xs">{{ $n['license_type'] ?? '—' }}<br><span class="text-ink-3">{{ $n['hosting_method'] ?? '' }}</span></td>
                    <td><x-admin.status :value="$item->status"/></td>
                    <td class="max-w-sm text-xs text-ink-2">@foreach ($item->errors ?? [] as $e)<div>• {{ $e }}</div>@endforeach</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-ink-3">{{ $batch->status === 'queued' ? 'Waiting for the queue worker to build the preview.' : 'No rows.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
