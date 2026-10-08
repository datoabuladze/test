{{-- Cookie consent. Only shown when an optional third-party service (GA4 or AdSense) is configured;
     the platform itself uses only essential cookies (session, CSRF). --}}
@if (config('platform.analytics.ga4_measurement_id') || config('platform.ads.adsense_client'))
<div x-data="consentBanner" x-cloak x-show="open" role="dialog" aria-modal="false" aria-labelledby="consent-title"
     class="fixed inset-x-3 bottom-3 z-[70] mx-auto max-w-xl rounded-2xl border border-line bg-card p-4 shadow-2xl sm:p-5">
    <h2 id="consent-title" class="font-bold">{{ __('Cookies on :brand', ['brand' => config('platform.brand')]) }}</h2>
    <p class="mt-1 text-sm text-ink-2">{{ __('We use essential cookies to run the site. With your permission we also use analytics and advertising cookies to understand usage and show ads.') }}
        <a href="{{ route('pages.show', ['slug' => 'cookies']) }}" class="text-brand-2 underline">{{ __('Cookie policy') }}</a></p>
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="btn-primary btn-sm" @click="acceptAll">{{ __('Accept all') }}</button>
        <button type="button" class="btn-ghost btn-sm" @click="essentialOnly">{{ __('Essential only') }}</button>
    </div>
</div>
@endif
