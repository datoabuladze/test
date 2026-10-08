@extends('layouts.admin')
@section('title', 'Pages & blog')
@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    <form method="get" class="flex flex-1 flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search title or slug" class="input max-w-xs">
        <select name="type" class="input w-auto">
            <option value="">All types</option>
            @foreach ($types as $t)<option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>@endforeach
        </select>
        <select name="status" class="input w-auto">
            <option value="">Any status</option>
            <option value="draft" @selected($status === 'draft')>Draft</option>
            <option value="published" @selected($status === 'published')>Published</option>
        </select>
        <button class="btn-ghost"><x-icon name="filter" class="size-4"/>Filter</button>
        @if ($q || $type || $status)<a href="{{ route('admin.pages.index') }}" class="text-sm text-ink-3 hover:text-ink">Reset</a>@endif
    </form>
    <a href="{{ route('admin.pages.create', $type ? ['type' => $type] : []) }}" class="btn-primary"><x-icon name="plus" class="size-4"/>New page</a>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Title</th><th>Type</th><th>Slug</th><th>Status</th><th>Published</th><th>Author</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        @forelse ($pages as $page)
            <tr>
                <td class="font-medium"><a href="{{ route('admin.pages.edit', $page) }}" class="hover:text-brand">{{ $page->tr('title') ?: '(untitled)' }}</a></td>
                <td><span class="chip">{{ $page->type }}</span></td>
                <td class="font-mono text-xs text-ink-3">{{ $page->slug }}</td>
                <td><x-admin.status :value="$page->status"/></td>
                <td class="text-xs text-ink-3 whitespace-nowrap">{{ $page->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td class="text-xs text-ink-3">{{ $page->author?->nickname ?? '—' }}</td>
                <td class="whitespace-nowrap text-right">
                    @if ($page->status === 'published')
                        <a href="{{ $page->url('en') }}" target="_blank" rel="noopener" class="btn-ghost btn-sm" title="View"><x-icon name="eye" class="size-4"/></a>
                    @endif
                    <a href="{{ route('admin.pages.edit', $page) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4"/>Edit</a>
                    <form method="post" action="{{ route('admin.pages.destroy', $page) }}" class="inline" x-data="confirmForm" data-confirm="Delete this page?" @submit="confirmSubmit">
                        @csrf @method('DELETE')
                        <button class="btn-danger btn-sm" title="Delete"><x-icon name="trash" class="size-4"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-ink-3">No pages found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $pages->links() }}</div>
@endsection
