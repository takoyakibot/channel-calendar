<div id="cookie-consent" hidden>
    <style>
        .cc-banner { position: fixed; bottom: 0; left: 0; right: 0; z-index: 200; background: #fff; border-top: 1px solid #e5e7eb; box-shadow: 0 -4px 16px rgb(0 0 0 / 0.08); padding: 1rem 1.5rem; }
        .cc-inner { max-width: 64rem; margin: 0 auto; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .cc-text { flex: 1; min-width: 240px; font-size: 0.875rem; color: #374151; line-height: 1.6; }
        .cc-text a { color: #2563eb; text-decoration: underline; }
        .cc-title { font-weight: 700; color: #111827; margin-bottom: 0.125rem; }
        .cc-current { display: block; margin-top: 0.25rem; font-size: 0.8125rem; color: #6b7280; }
        .cc-current strong { color: #111827; }
        .cc-actions { display: flex; gap: 0.5rem; flex: none; flex-wrap: wrap; align-items: center; }
        .cc-btn { padding: 0.5rem 1.25rem; border-radius: 0.375rem; font-size: 0.875rem; cursor: pointer; white-space: nowrap; }
        .cc-btn-all { background: #111827; color: #fff; border: none; }
        .cc-btn-all:hover { background: #374151; }
        .cc-btn-req { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .cc-btn-req:hover { background: #f3f4f6; }
        .cc-btn-close { background: transparent; color: #6b7280; border: none; text-decoration: underline; padding: 0.5rem 0.5rem; }
        .cc-btn-close:hover { color: #111827; }
        .cc-btn.is-current { outline: 2px solid #111827; outline-offset: 2px; }
        .cc-btn.is-current::after { content: " ✓"; }
        .cc-notice { position: fixed; bottom: 0; left: 0; right: 0; z-index: 199; background: #fefce8; border-top: 1px solid #fde68a; padding: 0.625rem 1.5rem; text-align: center; font-size: 0.8125rem; color: #92400e; }
        .cc-notice a { color: #2563eb; text-decoration: underline; cursor: pointer; }
    </style>
    <div class="cc-banner" id="cc-banner" role="dialog" aria-labelledby="cc-title" aria-describedby="cc-desc">
        <div class="cc-inner">
            <div class="cc-text">
                <div class="cc-title" id="cc-title">Cookie の使用について</div>
                <span id="cc-desc">このサイトでは、基本的な動作に必要な Cookie に加え、ログイン機能とアクセス解析（Google アナリティクス）のために任意の Cookie を使用しています。詳しくは<a href="{{ url('/privacy') }}#cookie">プライバシーポリシー</a>をご確認ください。</span>
                <span class="cc-current" id="cc-current" hidden>現在の設定: <strong id="cc-current-label"></strong></span>
            </div>
            <div class="cc-actions">
                <button type="button" class="cc-btn cc-btn-all" id="cc-accept-all">すべて許可</button>
                <button type="button" class="cc-btn cc-btn-req" id="cc-accept-req">必要なCookieのみ</button>
                <button type="button" class="cc-btn cc-btn-close" id="cc-close" hidden>変更せずに閉じる</button>
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
    var LABELS = { all: 'すべて許可', required: '必要なCookieのみ' };
    var banner = document.getElementById('cookie-consent');
    var notice = document.getElementById('cc-limit-notice');
    var titleEl = document.getElementById('cc-title');
    var currentEl = document.getElementById('cc-current');
    var currentLabel = document.getElementById('cc-current-label');
    var btnAll = document.getElementById('cc-accept-all');
    var btnReq = document.getElementById('cc-accept-req');
    var btnClose = document.getElementById('cc-close');

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
        notice.hidden = val !== 'required';
        window.dispatchEvent(new CustomEvent('cookieConsent', { detail: val }));
    }

    // First visit: ask for a choice. Settings: show the current choice and
    // allow changing it — or closing without touching anything.
    function showBanner(mode) {
        var consent = getConsent();
        var isSettings = mode === 'settings' && (consent === 'all' || consent === 'required');
        titleEl.textContent = isSettings ? 'Cookie 設定' : 'Cookie の使用について';
        currentEl.hidden = !isSettings;
        btnClose.hidden = !isSettings;
        if (isSettings) { currentLabel.textContent = LABELS[consent]; }
        btnAll.classList.toggle('is-current', isSettings && consent === 'all');
        btnReq.classList.toggle('is-current', isSettings && consent === 'required');
        btnAll.setAttribute('aria-pressed', String(isSettings && consent === 'all'));
        btnReq.setAttribute('aria-pressed', String(isSettings && consent === 'required'));
        banner.hidden = false;
    }
    function hideBanner() {
        banner.hidden = true;
    }
    function choose(val) {
        setConsent(val);
        hideBanner();
        applyConsent(val);
    }

    var consent = getConsent();
    if (consent === 'all' || consent === 'required') {
        applyConsent(consent);
    } else {
        applyConsent('none');
        showBanner('first');
    }

    btnAll.addEventListener('click', function () { choose('all'); });
    btnReq.addEventListener('click', function () { choose('required'); });
    btnClose.addEventListener('click', hideBanner);

    document.getElementById('cc-change-setting').addEventListener('click', function (e) {
        e.preventDefault();
        showBanner('settings');
    });
    document.querySelectorAll('[data-cc-reset]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            showBanner('settings');
        });
    });
})();
</script>
