@extends('layouts.admin')
@section('title', 'Providers')

@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <p class="max-w-2xl text-sm text-ink-3">A provider is a source of games with an agreement or license. Embedded games may only point at hosts on the provider's allow-list. Add a provider only after you have the right to list its games.</p>
    <a href="{{ route('admin.providers.create') }}" class="btn-primary shrink-0"><x-icon name="plus" class="size-4"/>New provider</a>
</div>
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Provider</th><th>Adapter</th><th>Embed hosts</th><th>Health</th><th class="text-right">Games</th><th>Last sync</th><th></th></tr></thead>
        <tbody>
        @forelse ($providers as $p)
            <tr>
                <td><a href="{{ route('admin.providers.edit', $p) }}" class="font-medium hover:text-brand-2">{{ $p->name }}</a> @unless ($p->is_active)<x-admin.status value="archived"/>@endunless
                    @if ($p->agreement_url)<div class="text-xs"><a href="{{ $p->agreement_url }}" class="text-brand-2" target="_blank" rel="noopener noreferrer">Agreement</a></div>@endif</td>
                <td class="text-xs">{{ $p->adapter }}</td>
                <td class="max-w-xs text-xs text-ink-2">{{ implode(', ', $p->allowed_embed_hosts ?? []) ?: '—' }}</td>
                <td><x-admin.status :value="$p->health_status"/></td>
                <td class="text-right tabular-nums">{{ $p->public_games_count }} / {{ $p->games_count }}</td>
                <td class="text-xs text-ink-3">{{ $p->last_synced_at?->diffForHumans() ?? 'never' }}</td>
                <td class="text-right"><a href="{{ route('admin.providers.edit', $p) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4"/></a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-ink-3">No providers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-ink-3">Games column: public / total.</p>
@endsection
