@extends('layouts.app')

@push('head')
    @vite('resources/js/room.js')
@endpush

@section('content')
<div class="container-page max-w-4xl pt-10">
    <div class="text-center">
        <span class="chip mx-auto"><x-icon name="users" class="size-3.5"/>{{ __('Two players') }}</span>
        <h1 class="mt-4 text-3xl font-black sm:text-5xl"><span class="text-gradient">{{ __('Play with a friend') }}</span></h1>
        <p class="mx-auto mt-3 max-w-xl text-ink-2">{{ __('Create a private room, send the link and play live. The server checks every move, so nobody can cheat.') }}</p>
    </div>
    <div class="mt-10 grid gap-5 sm:grid-cols-2" x-data="roomCreator" data-url="{{ route('api.rooms.store') }}">
        @foreach ([
            ['tictactoe', __('Tic-Tac-Toe'), __('Three in a row wins. Quick rounds, endless rematches.'), 'grid'],
            ['connect4', __('Four in a Row'), __('Drop discs and connect four before your friend does.'), 'gamepad'],
        ] as [$key, $name, $desc, $icon])
            <div class="card relative overflow-hidden p-6">
                <div class="absolute -right-10 -top-10 size-40 rounded-full bg-brand/20 blur-3xl"></div>
                <div class="relative">
                    <div class="flex size-12 items-center justify-center rounded-xl bg-brand/15 text-brand"><x-icon :name="$icon" class="size-6"/></div>
                    <h2 class="mt-4 text-xl font-bold">{{ $name }}</h2>
                    <p class="mt-1 text-sm text-ink-2">{{ $desc }}</p>
                    <button type="button" class="btn-primary mt-5" @click="create('{{ $key }}')" :disabled="busy" data-testid="create-{{ $key }}">
                        <x-icon name="plus" class="size-4"/>{{ __('Create room') }}
                    </button>
                </div>
            </div>
        @endforeach
        <p x-cloak x-show="error" class="text-sm text-bad sm:col-span-2" x-text="error"></p>
    </div>
</div>
@endsection
