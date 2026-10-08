@extends('layouts.app')
@section('content')
<div class="container-page pt-8">
    @include('account.nav')
    @if ($games->isEmpty())
        <x-empty-state :title="__('No favorites yet')" icon="heart">{{ __('Tap the heart on any game to save it here.') }}</x-empty-state>
    @else
        <x-game-grid :games="$games"/>
        {{ $games->links() }}
    @endif
</div>
@endsection
