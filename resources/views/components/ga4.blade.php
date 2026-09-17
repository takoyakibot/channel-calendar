@php($ga4Id = config('services.ga4.measurement_id'))
@if ($ga4Id)
{{-- Google Analytics 4. Loaded only once the visitor has accepted optional cookies
     ("すべて許可" in the cookie banner), and immediately when that consent is given
     later on the same page, so no _ga cookies are set before consent. --}}
<script>
(function () {
    var GA4_ID = @json($ga4Id);
    var CONSENT_KEY = 'cc.cookieConsent';
    var loaded = false;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };

    function loadGa4() {
        if (loaded) return;
        loaded = true;
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
        document.head.appendChild(s);
        gtag('js', new Date());
        gtag('config', GA4_ID, { anonymize_ip: true });
    }

    function hasConsent() {
        try { return localStorage.getItem(CONSENT_KEY) === 'all'; } catch (e) { return false; }
    }

    if (hasConsent()) loadGa4();
    window.addEventListener('cookieConsent', function (e) {
        if (e.detail === 'all') loadGa4();
    });
})();
</script>
@endif
