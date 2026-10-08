@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <h1 class="text-3xl font-black">
        @if ($q !== '') {{ __('Results for “:q”', ['q' => $q]) }} @else {{ __('Search games') }} @endif
    </h1>
    @if ($q !== '')
        <p class="mt-1 text-sm text-ink-2" data-testid="search-count">{{ trans_choice(':count game found|:count games found', $games->total(), ['count' => $games->total()]) }}</p>
    @endif
    @if ($corrected)
        <p class="mt-2 text-sm text-ink-2">{{ __('Showing results for') }} <a class="font-semibold text-brand-2 underline" href="{{ route('search', ['q' => $corrected]) }}">{{ $corrected }}</a></p>
    @endif

    @if ($trending)
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink-3">{{ __('Popular searches') }}</span>
            @foreach ($trending as $term)<a class="chip" href="{{ route('search', ['q' => $term]) }}">{{ $term }}</a>@endforeach
        </div>
    @endif

    <div class="mt-6">
        @if ($games->total() > 0)
            <x-game-grid :games="$games"/>
            {{ $games->links() }}
        @elseif ($q !== '')
            <x-empty-state :title="__('No games found')" icon="search">{{ __('Check the spelling or try a broader word like “racing” or “puzzle”.') }}</x-empty-state>
            <x-game-rail :title="__('Trending right now')" :games="$fallback" icon="fire" class="mt-8"/>
        @endif
    </div>
</div>
@endsection
