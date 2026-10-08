@extends('layouts.admin')
@section('title', $page->exists ? 'Edit page' : 'New page')
@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.pages.index') }}" class="btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4"/>All pages</a>
    @if ($page->exists && $page->status === 'published')
        <a href="{{ $page->url('en') }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-icon name="external" class="size-4"/>View live</a>
    @endif
</div>

<form method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="grid gap-6 xl:grid-cols-[1fr_320px]">
    @csrf
    @if ($page->exists) @method('PUT') @endif

    <div class="space-y-6">
        <div class="card space-y-5 p-5">
            <x-admin.translatable name="title" label="Title" :model="$page" required/>
            <div>
                <label class="label" for="f-slug">Slug</label>
                <input id="f-slug" name="slug" value="{{ old('slug', $page->slug) }}" class="input font-mono" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="auto from English title">
                <p class="help">Lowercase letters, numbers and dashes. Unique per type. Leave empty to generate from the English title.</p>
                @error('slug')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-admin.translatable name="excerpt" label="Excerpt" :model="$page" textarea :rows="2" help="Short summary shown in lists and used as a fallback meta description."/>
        </div>
        <div class="card space-y-3 p-5">
            <x-admin.translatable name="body" label="Body (Markdown)" :model="$page" textarea :rows="14" required
                help="Markdown: ## headings, **bold**, lists, [links](https://…). Raw HTML is stripped when rendered. Legal pages may use :brand, :operator, :contact_email, :jurisdiction and :updated tokens."/>
        </div>
        <div class="card space-y-5 p-5">
            <h2 class="font-semibold">Search engines</h2>
            <x-admin.translatable name="seo_title" label="SEO title" :model="$page" help="Defaults to the title."/>
            <x-admin.translatable name="seo_description" label="SEO description" :model="$page" textarea :rows="2" help="About 150–160 characters. Defaults to the excerpt."/>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="card space-y-4 p-5">
            <div>
                <label class="label" for="f-type">Type</label>
                <select id="f-type" name="type" class="input">
                    @foreach ($types as $t)<option value="{{ $t }}" @selected(old('type', $page->type) === $t)>{{ ucfirst($t) }}</option>@endforeach
                </select>
                <p class="help">Blog and news appear under /blog; other types under /p/slug.</p>
            </div>
            <div>
                <label class="label" for="f-status">Status</label>
                <select id="f-status" name="status" class="input">
                    <option value="draft" @selected(old('status', $page->status) === 'draft')>Draft</option>
                    <option value="published" @selected(old('status', $page->status) === 'published')>Published</option>
                </select>
            </div>
            <div>
                <label class="label" for="f-published">Publish date</label>
                <input id="f-published" type="datetime-local" name="published_at" value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}" class="input">
                <p class="help">A future date schedules the page. Empty = now when published.</p>
            </div>
            <button class="btn-primary w-full"><x-icon name="check" class="size-4"/>{{ $page->exists ? 'Save changes' : 'Create page' }}</button>
        </div>
        @if ($page->exists)
            <div class="card p-5 text-xs text-ink-3 space-y-1">
                <div>Created {{ $page->created_at?->format('Y-m-d H:i') }}</div>
                <div>Updated {{ $page->updated_at?->format('Y-m-d H:i') }}</div>
            </div>
        @endif
    </aside>
</form>
@if ($page->exists)
    <form method="post" action="{{ route('admin.pages.destroy', $page) }}" class="mt-6" x-data="confirmForm" data-confirm="Delete this page permanently?" @submit="confirmSubmit">
        @csrf @method('DELETE')
        <button class="btn-danger btn-sm"><x-icon name="trash" class="size-4"/>Delete page</button>
    </form>
@endif
@endsection
