@extends('layouts.admin')
@section('title', 'Preview · '.$game->tr('title', 'en'))

@push('head')
    @vite('resources/js/player.js')
@endpush

@php
    $title = $game->tr('title', 'en');
    $ratio = ($game->width && $game->height) ? $game->width.' / '.$game->height : '16 / 9';
@endphp

@section('content')
<div class="mx-auto max-w-5xl space-y-4">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-admin.status :value="$game->status"/><x-admin.status :value="$game->rights_status"/><x-admin.status :value="$game->launch_status"/>
        @if ($game->flash_compatibility)<x-admin.status :value="$game->flash_compatibility"/>@endif
        <span class="text-ink-3">{{ $game->engine->label() }} · {{ $game->entry_path ?? $game->embed_url }}</span>
        <a href="{{ route('admin.games.edit', $game) }}" class="btn-ghost btn-sm ml-auto"><x-icon name="edit" class="size-4"/>Back to edit</a>
    </div>
    <p class="rounded-lg bg-card-2 px-3 py-2 text-xs text-ink-2">Staff preview: runs in the same sandboxed player as the public site. Plays and scores are not recorded. Test controls, sound, fullscreen and mobile layout, then record the result (launch check, Flash compatibility) on the edit page.</p>
    @include('games.partials.player', ['preview' => true])
</div>
@endsection
