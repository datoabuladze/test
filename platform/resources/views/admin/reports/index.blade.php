@extends('layouts.admin')
@section('title', 'Reports')

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.reports.index', ['status' => $k]) }}" class="chip {{ $status === $k ? 'chip-active' : '' }}">{{ $l }}</a>
    @endforeach
</div>
@if ($byGame->isNotEmpty())
    <div class="card mb-4 p-4 text-sm"><span class="font-semibold">Most reported (open):</span>
        @foreach ($byGame as $b)<a href="{{ $b->game ? route('admin.games.edit', $b->game) : '#' }}" class="ml-3 text-brand-2">{{ $b->game?->tr('title', 'en') ?? 'deleted' }} ({{ $b->c }})</a>@endforeach
    </div>
@endif
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Game</th><th>Reason</th><th>Message</th><th>From</th><th>When</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($reports as $r)
            <tr>
                <td>@if ($r->game)<a href="{{ route('admin.games.edit', $r->game) }}" class="font-medium hover:text-brand-2">{{ $r->game->tr('title', 'en') }}</a>@else<span class="text-ink-3">deleted</span>@endif</td>
                <td><span class="badge {{ $r->reason === 'copyright' ? 'bg-bad/15 text-bad' : 'bg-card-2 text-ink-2' }}">{{ str_replace('_', ' ', $r->reason) }}</span></td>
                <td class="max-w-md text-sm text-ink-2">{{ $r->message }}</td>
                <td class="text-xs">{{ $r->user?->nickname ?? 'guest' }}</td>
                <td class="text-xs text-ink-3">{{ $r->created_at->diffForHumans() }}</td>
                <td><x-admin.status :value="$r->status"/></td>
                <td class="whitespace-nowrap text-right">
                    @foreach (['resolved' => 'Resolve', 'dismissed' => 'Dismiss', 'open' => 'Reopen'] as $s => $l)
                        @if ($r->status !== $s)
                            <form method="post" action="{{ route('admin.reports.update', $r) }}" class="inline">@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="btn-ghost btn-sm">{{ $l }}</button></form>
                        @endif
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-ink-3">No reports.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $reports->links() }}</div>
<p class="mt-3 text-xs text-ink-3">Copyright reports: unpublish the game first, then follow the takedown procedure in docs/OPERATIONS.md.</p>
@endsection
