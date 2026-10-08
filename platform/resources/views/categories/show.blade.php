@extends('layouts.app')

@section('content')
@php $crumbs = [[__('Home'), route('home')], [__('Categories'), route('categories.index')]];
    foreach ($category->ancestry() as $node) { $crumbs[] = [$node->tr('name'), $node->url()]; } @endphp
<div class="container-page pt-6">
    <x-breadcrumbs :items="$crumbs" class="mb-3"/>
    <div class="relative mb-6 overflow-hidden rounded-3xl border border-line bg-card p-6 sm:p-8">
        <div class="absolute -right-16 -top-16 size-64 rounded-full opacity-30 blur-3xl" style="background: {{ $category->color ?: '#7c5cff' }}"></div>
        <div class="relative">
            <h1 class="text-3xl font-black sm:text-4xl">{{ __(':category games', ['category' => $category->tr('name')]) }}</h1>
            @if ($category->tr('description'))
                <p class="mt-2 max-w-2xl text-sm text-ink-2">{{ $category->tr('description') }}</p>
            @endif
            @if ($category->children->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($category->children as $child)<a class="chip" href="{{ $child->url() }}">{{ $child->tr('name') }}</a>@endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-2">{{ trans_choice(':count game|:count games', $games->total(), ['count' => number_format($games->total())]) }}</p>
        @include('partials.listing-toolbar')
    </div>
    <x-ad-slot placement="category_top" class="mb-6"/>
    @if ($games->isEmpty())
        <x-empty-state :title="__('No games here yet')">{{ __('We are adding new games regularly. Try another category in the meantime.') }}</x-empty-state>
    @else
        <x-game-grid :games="$games"/>
        {{ $games->links() }}
    @endif
</div>
@endsection
