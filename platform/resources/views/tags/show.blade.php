@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <x-breadcrumbs :items="[[__('Home'), route('home')], ['#'.$tag->tr('name'), null]]" class="mb-3"/>
    <h1 class="mb-6 text-3xl font-black">{{ __(':tag games', ['tag' => $tag->tr('name')]) }}</h1>
    @if ($games->isEmpty())
        <x-empty-state :title="__('No games here yet')">{{ __('Try another tag.') }}</x-empty-state>
    @else
        <x-game-grid :games="$games"/>
        {{ $games->links() }}
    @endif
</div>
@endsection
