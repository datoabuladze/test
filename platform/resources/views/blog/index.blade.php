@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <h1 class="mb-6 text-3xl font-black">{{ __('News & guides') }}</h1>
    @if ($posts->isEmpty())
        <x-empty-state :title="__('No articles yet')" icon="file">{{ __('Check back soon for news and guides.') }}</x-empty-state>
    @else
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <a href="{{ $post->url() }}" class="card group p-6 transition hover:-translate-y-0.5 hover:shadow-glow">
                    <span class="badge bg-brand/15 text-brand-2">{{ __(ucfirst($post->type)) }}</span>
                    <h2 class="mt-3 text-lg font-bold group-hover:text-brand-2">{{ $post->tr('title') }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm text-ink-2">{{ $post->tr('excerpt') }}</p>
                    <p class="mt-4 text-xs text-ink-3">{{ $post->published_at->translatedFormat('j M Y') }}</p>
                </a>
            @endforeach
        </div>
        {{ $posts->links() }}
    @endif
</div>
@endsection
