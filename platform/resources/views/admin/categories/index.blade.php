@extends('layouts.admin')
@section('title', 'Categories')

@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <p class="text-sm text-ink-3">Drag rows (or use the arrows) to change the order shown in menus and on the homepage. Subcategories are ordered within their parent.</p>
    <a href="{{ route('admin.categories.create') }}" class="btn-primary shrink-0"><x-icon name="plus" class="size-4"/>New category</a>
</div>

@php
    $row = function ($c, $child = false) {
        return view('admin.categories.row', ['c' => $c, 'child' => $child])->render();
    };
@endphp

<ul x-data="sortableList" data-url="{{ route('admin.categories.reorder') }}" class="space-y-2">
    @foreach ($roots as $c)
        <li data-id="{{ $c->id }}" draggable="true" class="card p-3">
            {!! $row($c) !!}
            @if ($children->has($c->id))
                <ul x-data="sortableList" data-url="{{ route('admin.categories.reorder') }}" class="mt-2 space-y-1.5 border-l border-line pl-4">
                    @foreach ($children[$c->id] as $sub)
                        <li data-id="{{ $sub->id }}" draggable="true" class="rounded-lg bg-card-2 p-2">{!! $row($sub, true) !!}</li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ul>
@endsection
