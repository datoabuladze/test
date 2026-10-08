@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <x-breadcrumbs :items="[[__('Home'), route('home')], [__('Categories'), null]]" class="mb-3"/>
    <h1 class="mb-6 text-3xl font-black">{{ __('Game categories') }}</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($categories as $cat)
            <div class="card group relative overflow-hidden p-5">
                <div class="absolute -right-8 -top-8 size-32 rounded-full opacity-25 blur-2xl transition group-hover:opacity-50" style="background: {{ $cat->color ?: '#7c5cff' }}"></div>
                <a href="{{ $cat->url() }}" class="relative block">
                    <h2 class="text-lg font-bold">{{ $cat->tr('name') }}</h2>
                    <p class="text-xs text-ink-3">{{ trans_choice(':count game|:count games', $cat->games_count) }}</p>
                </a>
                @if ($cat->children->isNotEmpty())
                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        @foreach ($cat->children as $child)
                            <a class="chip" href="{{ $child->url() }}">{{ $child->tr('name') }} <span class="text-ink-3">{{ $child->games_count }}</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
