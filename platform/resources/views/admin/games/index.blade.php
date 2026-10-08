@extends('layouts.admin')
@section('title', request()->boolean('trashed') ? 'Games · trash' : 'Games')

@section('content')
@php $canPublish = auth()->user()->hasPermission('games.publish'); @endphp
<div class="mb-4 flex flex-wrap items-center gap-2">
    <form method="get" class="flex flex-1 flex-wrap items-center gap-2">
        @if (request()->boolean('trashed'))<input type="hidden" name="trashed" value="1">@endif
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search title, slug or external id" class="input max-w-xs">
        <select name="status" class="input w-auto">
            <option value="">Any status</option>
            @foreach (\App\Enums\GameStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ ucfirst($s->value) }}</option>@endforeach
        </select>
        <select name="rights_status" class="input w-auto">
            <option value="">Any rights</option>
            @foreach (\App\Enums\RightsStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('rights_status') === $s->value)>{{ ucfirst($s->value) }}</option>@endforeach
        </select>
        <select name="launch_status" class="input w-auto">
            <option value="">Any launch</option>
            @foreach (['untested', 'ok', 'failed'] as $s)<option value="{{ $s }}" @selected(request('launch_status') === $s)>{{ ucfirst($s) }}</option>@endforeach
        </select>
        <select name="engine" class="input w-auto">
            <option value="">Any engine</option>
            @foreach (\App\Enums\GameEngine::cases() as $e)<option value="{{ $e->value }}" @selected(request('engine') === $e->value)>{{ $e->label() }}</option>@endforeach
        </select>
        <select name="provider" class="input w-auto">
            <option value="">Any provider</option>
            @foreach ($providers as $p)<option value="{{ $p->id }}" @selected((string) request('provider') === (string) $p->id)>{{ $p->name }}</option>@endforeach
        </select>
        <select name="category" class="input w-auto">
            <option value="">Any category</option>
            @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->tr('name', 'en') }}</option>@endforeach
        </select>
        <select name="sort" class="input w-auto">
            @foreach (['updated' => 'Recently updated', 'published' => 'Recently published', 'plays' => 'Most played', 'title' => 'Slug A–Z'] as $k => $l)<option value="{{ $k }}" @selected(request('sort', 'updated') === $k)>{{ $l }}</option>@endforeach
        </select>
        <button class="btn-ghost"><x-icon name="filter" class="size-4"/>Filter</button>
    </form>
    <a href="{{ route('admin.games.index', request()->boolean('trashed') ? [] : ['trashed' => 1]) }}" class="btn-ghost"><x-icon name="trash" class="size-4"/>{{ request()->boolean('trashed') ? 'Back to games' : 'Trash' }}</a>
    <a href="{{ route('admin.games.create') }}" class="btn-primary"><x-icon name="plus" class="size-4"/>New game</a>
</div>

<form method="post" action="{{ route('admin.games.bulk') }}" x-data="bulkTable" class="card overflow-hidden">
    @csrf
    <div class="flex flex-wrap items-center gap-2 border-b border-line p-3 text-sm">
        <span class="text-ink-3" x-text="selectionLabel">0 selected</span>
        <select name="action" class="input w-auto" required>
            <option value="">Bulk action…</option>
            @if ($canPublish)<option value="publish">Publish</option><option value="unpublish">Unpublish</option>@endif
            <option value="feature">Feature</option>
            <option value="unfeature">Unfeature</option>
            <option value="check">Run launch check</option>
            <option value="category">Add to category…</option>
            <option value="trash">Move to trash</option>
        </select>
        <select name="category_id" class="input w-auto">
            <option value="">(category for “Add to category”)</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->tr('name', 'en') }}</option>@endforeach
        </select>
        <button class="btn-ghost btn-sm" :disabled="!hasSelection">Apply</button>
        <span class="ml-auto text-ink-3">{{ number_format($games->total()) }} games</span>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr>
                <th class="w-8"><input type="checkbox" aria-label="Select all" @change="toggleAll"></th>
                <th>Game</th><th>Engine</th><th>Status</th><th>Rights</th><th>Launch</th><th>Provider</th><th class="text-right">Plays</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($games as $g)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $g->id }}" aria-label="Select {{ $g->tr('title', 'en') }}"></td>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-12 shrink-0 overflow-hidden rounded-md bg-card-2"><x-game-thumb :game="$g" sizes="48px"/></div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.games.edit', $g) }}" class="font-medium hover:text-brand-2">{{ $g->tr('title', 'en') }}</a>
                                @if ($g->is_featured)<span class="badge bg-brand/20 text-brand-2">featured</span>@endif
                                <div class="text-xs text-ink-3">{{ $g->slug }} · {{ $g->categories->map(fn ($c) => $c->tr('name', 'en'))->join(', ') ?: 'no category' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-xs">{{ $g->engine->label() }}@if ($g->flash_compatibility)<br><x-admin.status :value="$g->flash_compatibility"/>@endif</td>
                    <td><x-admin.status :value="$g->status"/>@if ($g->published_at?->isFuture())<div class="text-[11px] text-ink-3">{{ $g->published_at->toDateString() }}</div>@endif</td>
                    <td><x-admin.status :value="$g->rights_status"/></td>
                    <td><x-admin.status :value="$g->launch_status"/></td>
                    <td class="text-xs text-ink-2">{{ $g->provider?->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ number_format($g->play_count) }}</td>
                    <td class="whitespace-nowrap text-right">
                        @if ($g->trashed())
                            <button form="restore-{{ $g->id }}" class="btn-ghost btn-sm">Restore</button>
                        @else
                            <a href="{{ route('admin.games.preview', $g) }}" class="btn-ghost btn-sm" title="Preview"><x-icon name="eye" class="size-4"/></a>
                            <a href="{{ route('admin.games.edit', $g) }}" class="btn-ghost btn-sm" title="Edit"><x-icon name="edit" class="size-4"/></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-ink-3">No games match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
@foreach ($games as $g)
    @if ($g->trashed())
        <form id="restore-{{ $g->id }}" method="post" action="{{ route('admin.games.restore', $g) }}" class="hidden">@csrf</form>
    @endif
@endforeach
<div class="mt-4">{{ $games->links() }}</div>
@endsection
