@extends('layouts.app')

@section('content')
<div class="container-page max-w-3xl pt-8">
    <x-breadcrumbs :items="[[__('Home'), route('home')], [__('News & guides'), route('blog.index')], [$post->tr('title'), null]]" class="mb-4"/>
    <article class="card p-6 sm:p-10">
        <p class="text-xs text-ink-3">{{ $post->published_at->translatedFormat('j F Y') }}</p>
        <h1 class="mt-2 text-3xl font-black">{{ $post->tr('title') }}</h1>
        <div class="prose-content mt-6">{!! \Illuminate\Support\Str::markdown($post->tr('body'), ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    </article>
</div>
@endsection
