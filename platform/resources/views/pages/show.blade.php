@extends('layouts.app')

@section('content')
<div class="container-page max-w-3xl pt-8">
    <x-breadcrumbs :items="[[__('Home'), route('home')], [$page->tr('title'), null]]" class="mb-4"/>
    <article class="card p-6 sm:p-10">
        <h1 class="text-3xl font-black">{{ $page->tr('title') }}</h1>
        @if ($page->type === 'legal')
            <p class="mt-2 text-xs text-ink-3">{{ __('Last updated: :date', ['date' => $page->updated_at->translatedFormat('j F Y')]) }}</p>
        @endif
        <div class="prose-content mt-6">{!! \Illuminate\Support\Str::markdown(\App\Support\ContentTokens::apply($page->tr('body')), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    </article>
</div>
@endsection
