@props(['game', 'eager' => false, 'sizes' => '(min-width: 1280px) 16vw, (min-width: 768px) 25vw, 45vw'])
@php
    $url = $game->thumbnailUrl();
    $color = $game->thumbnail_color ?: '#7c5cff';
    $title = $game->tr('title');
@endphp
@if ($url)
    <img src="{{ $url }}" alt="{{ $title }}" width="512" height="384" sizes="{{ $sizes }}"
         @if ($eager) fetchpriority="high" @else loading="lazy" decoding="async" @endif
         {{ $attributes->merge(['class' => 'h-full w-full object-cover']) }}>
@else
    {{-- Generated cover when no licensed thumbnail exists. --}}
    <div {{ $attributes->merge(['class' => 'relative flex h-full w-full items-center justify-center overflow-hidden']) }}
         style="background: radial-gradient(120% 90% at 20% 10%, {{ $color }}, color-mix(in oklab, {{ $color }} 35%, #0b0d17));">
        <span class="font-display text-4xl font-black text-white/90 drop-shadow">{{ mb_strtoupper(mb_substr($title, 0, 2)) }}</span>
    </div>
@endif
