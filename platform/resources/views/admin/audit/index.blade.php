@extends('layouts.admin')
@section('title', 'Audit log')

@section('content')
<form method="get" class="mb-4 flex flex-wrap gap-2">
    <select name="action" class="input w-auto"><option value="">All actions</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select>
    <input name="subject" value="{{ request('subject') }}" class="input w-40" placeholder="Subject type (Game)">
    <button class="btn-ghost">Filter</button>
</form>
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td class="whitespace-nowrap text-xs">{{ $l->created_at?->toDateTimeString() }}</td>
                <td class="text-sm">{{ $l->user?->nickname ?? 'system' }}</td>
                <td><code class="text-xs">{{ $l->action }}</code></td>
                <td class="text-xs">{{ $l->subject_type }} {{ $l->subject_id ? '#'.$l->subject_id : '' }}</td>
                <td class="max-w-md truncate font-mono text-[11px] text-ink-3" title="{{ json_encode($l->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">{{ $l->meta ? \Illuminate\Support\Str::limit(json_encode($l->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 140) : '' }}</td>
                <td class="text-xs text-ink-3">{{ $l->ip }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-ink-3">No entries.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
