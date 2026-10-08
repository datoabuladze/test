{{-- GA4 loads only when configured AND the visitor has accepted analytics cookies. --}}
@if ($ga = config('platform.analytics.ga4_measurement_id'))
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () {
        var consent = null;
        try { consent = localStorage.getItem('consent'); } catch (e) {}
        if (consent !== 'all') return;
        var s = document.createElement('script');
        s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id={{ $ga }}';
        document.head.appendChild(s);
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date()); gtag('config', '{{ $ga }}', { anonymize_ip: true });
    })();
</script>
@endif
