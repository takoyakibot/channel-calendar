<div id="cookie-consent" hidden>
    <style>
        .cc-banner { position: fixed; bottom: 0; left: 0; right: 0; z-index: 200; background: #fff; border-top: 1px solid #e5e7eb; box-shadow: 0 -4px 16px rgb(0 0 0 / 0.08); padding: 1rem 1.5rem; }
        .cc-inner { max-width: 64rem; margin: 0 auto; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .cc-text { flex: 1; min-width: 240px; font-size: 0.875rem; color: #374151; line-height: 1.6; }
        .cc-text a { color: #2563eb; text-decoration: underline; }
        .cc-actions { display: flex; gap: 0.5rem; flex: none; flex-wrap: wrap; }
        .cc-btn { padding: 0.5rem 1.25rem; border-radius: 0.375rem; font-size: 0.875rem; cursor: pointer; white-space: nowrap; }
        .cc-btn-all { background: #111827; color: #fff; border: none; }
        .cc-btn-all:hover { background: #374151; }
        .cc-btn-req { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .cc-btn-req:hover { background: #f3f4f6; }
        .cc-notice { position: fixed; bottom: 0; left: 0; right: 0; z-index: 199; background: #fefce8; border-top: 1px solid #fde68a; padding: 0.625rem 1.5rem; text-align: center; font-size: 0.8125rem; color: #92400e; }
        .cc-notice a { color: #2563eb; text-decoration: underline; cursor: pointer; }
    </style>
    <div class="cc-banner" id="cc-banner">
        <div class="cc-inner">
            <div class="cc-text">
                このサイトでは、基本的な動作に必要な Cookie に加え、ログイン機能のために任意の Cookie を使用しています。詳しくは<a href="{{ url('/privacy') }}#cookie">プライバシーポリシー</a>をご確認ください。
            </div>
            <div class="cc-actions">
                <button type="button" class="cc-btn cc-btn-all" id="cc-accept-all">すべて許可</button>
                <button type="button" class="cc-btn cc-btn-req" id="cc-accept-req">必要なCookieのみ</button>
            </div>
        </div>
    </div>
</div>
<div id="cc-limit-notice" class="cc-notice" hidden>
    Cookie の設定により、ログイン機能は無効になっています。<a id="cc-change-setting">Cookie 設定を変更</a>
</div>
<script>
(function () {
    var STORAGE_KEY = 'cc.cookieConsent';
    var banner = document.getElementById('cookie-consent');
    var bannerInner = document.getElementById('cc-banner');
    var notice = document.getElementById('cc-limit-notice');

    function getConsent() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
    }
    function setConsent(val) {
        try { localStorage.setItem(STORAGE_KEY, val); } catch (e) {}
    }

    function applyConsent(val) {
        document.querySelectorAll('[data-cc-login]').forEach(function (el) {
            el.hidden = val !== 'all';
        });
        if (val === 'required') {
            notice.hidden = false;
        } else {
            notice.hidden = true;
        }
        window.dispatchEvent(new CustomEvent('cookieConsent', { detail: val }));
    }

    function showBanner() {
        banner.hidden = false;
    }
    function hideBanner() {
        banner.hidden = true;
    }

    var consent = getConsent();
    if (!consent) {
        showBanner();
        applyConsent('none');
    } else {
        applyConsent(consent);
    }

    document.getElementById('cc-accept-all').addEventListener('click', function () {
        setConsent('all');
        hideBanner();
        applyConsent('all');
    });
    document.getElementById('cc-accept-req').addEventListener('click', function () {
        setConsent('required');
        hideBanner();
        applyConsent('required');
    });

    document.getElementById('cc-change-setting').addEventListener('click', function () {
        setConsent('');
        localStorage.removeItem(STORAGE_KEY);
        notice.hidden = true;
        showBanner();
        applyConsent('none');
    });

    document.querySelectorAll('[data-cc-reset]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            setConsent('');
            localStorage.removeItem(STORAGE_KEY);
            notice.hidden = true;
            showBanner();
            applyConsent('none');
        });
    });
})();
</script>
