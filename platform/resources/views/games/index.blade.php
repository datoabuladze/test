@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <x-breadcrumbs :items="[[__('Home'), route('home')], [__('All games'), null]]" class="mb-3"/>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-black">{{ __('All games') }}</h1>
            <p class="mt-1 text-sm text-ink-2">{{ trans_choice(':count free game to play right now|:count free games to play right now', $games->total(), ['count' => number_format($games->total())]) }}</p>
        </div>
        @include('partials.listing-toolbar')
    </div>
    @if ($games->isEmpty())
        <x-empty-state :title="__('No games match these filters')">{{ __('Try removing a filter.') }}</x-empty-state>
    @else
        <x-game-grid :games="$games"/>
        {{ $games->links() }}
    @endif
</div>
@endsection
