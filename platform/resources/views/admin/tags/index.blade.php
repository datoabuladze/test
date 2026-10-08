@extends('layouts.admin')
@section('title', 'Tags')

@section('content')
<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
    <div>
        <form method="get" class="mb-3 flex gap-2"><input type="search" name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Search tags"><button class="btn-ghost">Search</button></form>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tag</th><th>Translations</th><th class="text-right">Games</th><th></th></tr></thead>
                <tbody>
                @forelse ($tags as $t)
                    <tr x-data="disclosure">
                        <td><span class="font-medium">{{ $t->tr('name', 'en') }}</span> <span class="text-xs text-ink-3">#{{ $t->slug }}</span></td>
                        <td class="text-xs text-ink-3">{{ collect($t->name ?? [])->keys()->join(', ') }}</td>
                        <td class="text-right tabular-nums">{{ $t->games_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="btn-ghost btn-sm" @click="toggle" aria-label="Edit"><x-icon name="edit" class="size-4"/></button>
                            <form method="post" action="{{ route('admin.tags.destroy', $t) }}" class="inline" x-data="confirmForm" data-confirm="Delete tag #{{ $t->slug }}?" @submit="confirmSubmit">@csrf @method('delete')<button class="btn-ghost btn-sm" aria-label="Delete"><x-icon name="trash" class="size-4"/></button></form>
                            <div x-cloak x-show="open" class="mt-2 text-left">
                                <form method="post" action="{{ route('admin.tags.update', $t) }}" class="space-y-2">@csrf @method('put')
                                    <x-admin.translatable name="name" label="Name" :model="$t" required/>
                                    <input name="slug" value="{{ $t->slug }}" class="input font-mono">
                                    <button class="btn-primary btn-sm">Save</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-ink-3">No tags yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $tags->links() }}</div>
    </div>
    <form method="post" action="{{ route('admin.tags.store') }}" class="card h-fit space-y-3 p-4">
        @csrf
        <h2 class="font-bold">New tag</h2>
        <x-admin.translatable name="name" label="Name" required/>
        <div><label class="label" for="slug">Slug (optional)</label><input id="slug" name="slug" class="input font-mono"></div>
        <button class="btn-primary">Create tag</button>
    </form>
</div>
@endsection
