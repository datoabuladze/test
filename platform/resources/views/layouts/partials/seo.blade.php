@php $nonce = \Illuminate\Support\Facades\Vite::cspNonce(); @endphp
<title>{{ $seo->fullTitle() }}</title>
@if ($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
<meta name="robots" content="{{ $seo->index ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
<link rel="canonical" href="{{ $seo->canonicalUrl() }}">
@foreach ($seo->alternateUrls() as $hreflang => $href)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach
<meta property="og:site_name" content="{{ config('platform.brand') }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->title ?: config('platform.brand') }}">
@if ($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
<meta property="og:url" content="{{ $seo->canonicalUrl() }}">
<meta property="og:locale" content="{{ app()->getLocale() }}">
@if ($seo->image)<meta property="og:image" content="{{ $seo->image }}">@endif
<meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->title ?: config('platform.brand') }}">
@if ($seo->description)<meta name="twitter:description" content="{{ $seo->description }}">@endif
@if ($seo->image)<meta name="twitter:image" content="{{ $seo->image }}">@endif
@if ($v = config('platform.analytics.search_console_verification'))<meta name="google-site-verification" content="{{ $v }}">@endif
@foreach ($seo->jsonLd as $ld)
<script type="application/ld+json" nonce="{{ $nonce }}">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endforeach
