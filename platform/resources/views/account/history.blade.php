@extends('layouts.app')
@section('content')
<div class="container-page pt-8">
    @include('account.nav')
    @if ($plays->isEmpty())
        <x-empty-state :title="__('No games played yet')" icon="history">{{ __('Your play history will appear here.') }}</x-empty-state>
    @else
        <div class="card overflow-hidden">
            <table class="table">
                <thead><tr><th>{{ __('Game') }}</th><th>{{ __('When') }}</th><th class="text-right">{{ __('Time played') }}</th></tr></thead>
                <tbody>
                @foreach ($plays as $play)
                    <tr>
                        <td>@if ($play->game)<a class="hover:text-brand-2" href="{{ $play->game->url() }}">{{ $play->game->tr('title') }}</a>@else <span class="text-ink-3">{{ __('Removed game') }}</span>@endif</td>
                        <td class="text-ink-2">{{ $play->created_at->diffForHumans() }}</td>
                        <td class="text-right tabular-nums text-ink-2">{{ $play->duration_seconds ? gmdate($play->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $play->duration_seconds) : '–' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $plays->links() }}
    @endif
</div>
@endsection
