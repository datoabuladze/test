@extends('layouts.admin')
@section('title', $category->exists ? 'Edit category · '.$category->tr('name', 'en') : 'New category')

@section('content')
<form method="post" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="max-w-4xl space-y-6" x-data="slugger">
    @csrf
    @if ($category->exists) @method('put') @endif
    <section class="card space-y-4 p-5">
        <x-admin.translatable name="name" label="Name" :model="$category" required/>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="slug">Slug</label><input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="input font-mono" data-slug required maxlength="80"></div>
            <div><label class="label" for="parent_id">Parent</label>
                <select id="parent_id" name="parent_id" class="input"><option value="">Top level</option>
                    @foreach ($parents as $p)<option value="{{ $p->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $p->id)>{{ $p->tr('name', 'en') }}</option>@endforeach
                </select></div>
            <div><label class="label" for="color">Accent color</label><input id="color" name="color" value="{{ old('color', $category->color) }}" class="input font-mono" placeholder="#7c5cff"></div>
            <div><label class="label" for="icon">Icon name</label><input id="icon" name="icon" value="{{ old('icon', $category->icon) }}" class="input font-mono" placeholder="gamepad"></div>
        </div>
        <div class="flex flex-wrap gap-5 text-sm">
            @foreach (['is_active' => 'Active', 'show_in_menu' => 'Show in menu', 'show_on_home' => 'Show a rail on the homepage'] as $f => $l)
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="{{ $f }}" value="1" @checked(old($f, $category->$f))>{{ $l }}</label>
            @endforeach
        </div>
    </section>
    <section class="card space-y-4 p-5">
        <h2 class="font-bold">Landing page content & SEO</h2>
        <x-admin.translatable name="description" label="Intro text (shown above the games)" :model="$category" textarea :rows="3"/>
        <x-admin.translatable name="seo_title" label="SEO title" :model="$category"/>
        <x-admin.translatable name="seo_description" label="SEO description" :model="$category"/>
    </section>
    <div class="flex gap-2"><button class="btn-primary">Save</button><a href="{{ route('admin.categories.index') }}" class="btn-ghost">Cancel</a></div>
</form>
@if ($category->exists)
    <form method="post" action="{{ route('admin.categories.destroy', $category) }}" class="mt-6 max-w-4xl" x-data="confirmForm" data-confirm="Delete this category? Games stay in the catalog." @submit="confirmSubmit">
        @csrf @method('delete')<button class="btn-danger btn-sm"><x-icon name="trash" class="size-4"/>Delete category</button>
    </form>
@endif
@endsection
