@extends('layouts.app')

@section('content')
    <div class="container-page">
        <h1 class="sr-only">{{ config('platform.brand') }} – {{ __('Free online games') }}</h1>

        @if ($blocks->isEmpty())
            <div class="py-16">
                <x-empty-state :title="__('Games are on their way')">{{ __('No games have been published yet. Check back soon!') }}</x-empty-state>
            </div>
        @endif

        @foreach ($blocks as $block)
            @php $title = $block['section']->tr('title') ?: ($block['data']['fallbackTitle'] ?? $block['section']->type->label()); @endphp
            @include('home.'.$block['view'], ['title' => $title, 'section' => $block['section']] + $block['data'])
        @endforeach
    </div>
@endsection
