<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $pageTitle = $group ? $group->name . ' | ' . config('app.name', 'Channel Calendar') : config('app.name', 'Channel Calendar');
        $pageUrl = $group ? url('/' . $group->path) : url('/');
        $pageDescription = $group
            ? $group->name . 'の配信スケジュール - ' . config('app.name', 'Channel Calendar')
            : config('app.name', 'Channel Calendar') . ' - 配信スケジュールをまとめてチェック';
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'Channel Calendar') }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    @vite(['resources/css/app.css'])
    <style>
        /* Class rules below (e.g. .modal-overlay { display:flex }) would otherwise
           outrank the preflight [hidden] rule and keep hidden elements visible. */
        [hidden] { display: none !important; }
        .card-source { margin-top: 0.3rem; font-size: 0.6875rem; color: #2563eb; }
        .card:hover .card-source { text-decoration: underline; }

        @keyframes cc-new-flash {
            0%   { background: #fef08a; box-shadow: 0 0 0 3px #facc15; }
            60%  { background: #fef9c3; box-shadow: 0 0 0 3px #fde047; }
            100% { background: #fff; box-shadow: 0 0 0 0 transparent; }
        }
        .card.is-new { animation: cc-new-flash 4s ease-out forwards; }
        .fc-event.is-new { animation: cc-new-flash 4s ease-out forwards; border-radius: 0.25rem; }

        .filter-section { margin-bottom: 1rem; }
        .filter-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .x-group-search { display: inline-flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.8125rem; }
        .x-group-search a { color: #374151; text-decoration: none; padding: 0.25rem 0.75rem; border: 1px solid #d1d5db; border-radius: 9999px; background: #fff; }
        .x-group-search a:hover { background: #f3f4f6; color: #111827; }
        .filter-toggle { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0; font-size: 0.875rem; font-weight: 600; color: #374151; background: none; border: none; cursor: pointer; }
        .filter-toggle:hover { color: #111827; }
        .filter-toggle .arrow { display: inline-block; transition: transform 0.15s; font-size: 0.75rem; }
        .filter-toggle .arrow.collapsed { transform: rotate(-90deg); }
        .channel-filter { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .channel-filter label { display: flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; }
        .channel-filter label:hover { background-color: #f3f4f6; }
        .channel-filter img { width: 1.25rem; height: 1.25rem; border-radius: 50%; object-fit: cover; }
        .channel-filter .x-link { margin-left: 0.25rem; font-size: 0.75rem; color: #6b7280; text-decoration: none; padding: 0 0.2rem; border-radius: 0.25rem; }
        .channel-filter .x-link:hover { color: #111827; background: #e5e7eb; }
        .modal-hint { margin: 0.375rem 0 0; font-size: 0.75rem; color: #6b7280; display: flex; flex-direction: column; gap: 0.25rem; }
        .modal-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #111827; cursor: pointer; }
        .modal-hint-text { font-size: 0.75rem; color: #6b7280; }
        .card-meta .time.all-day { font-size: 0.8125rem; color: #6b7280; font-weight: 600; }
        .modal-hint a { color: #2563eb; text-decoration: none; }
        .modal-hint a:hover { text-decoration: underline; }

        .view-toggle { display: inline-flex; border: 1px solid #d1d5db; border-radius: 0.5rem; overflow: hidden; background: #fff; }
        .view-toggle button { padding: 0.375rem 0.875rem; font-size: 0.875rem; color: #374151; background: transparent; border: 0; cursor: pointer; }
        .view-toggle button + button { border-left: 1px solid #d1d5db; }
        .view-toggle button.is-active { background: #111827; color: #fff; }

        .page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .page-head-main { min-width: 0; }
        .page-head-actions { display: flex; align-items: center; gap: 1rem; }
        .title-row { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }
        .back-link { font-size: 1.25rem; color: #6b7280; text-decoration: none; line-height: 1; }
        .back-link:hover { color: #111827; }
        .title-row h1 { font-size: 1.5rem; font-weight: 700; color: #111827; line-height: 1.2; margin: 0; }
        .subgroup-toggles { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-bottom: 0.75rem; }
        .subgroup-toggles button { padding: 0.3rem 0.75rem; font-size: 0.8125rem; border: 1px solid #d1d5db; border-radius: 9999px; background: #fff; color: #374151; cursor: pointer; transition: background 0.1s, color 0.1s; }
        .subgroup-toggles button:hover { background: #f3f4f6; }
        .subgroup-toggles button.is-active { background: #111827; color: #fff; border-color: #111827; }
        .subgroup-hint { align-self: center; font-size: 0.75rem; color: #6b7280; margin-left: 0.25rem; }
        .admin-link { font-size: 0.875rem; color: #2563eb; text-decoration: none; }
        .admin-link:hover { text-decoration: underline; }
        .share-buttons { display: inline-flex; gap: 0.375rem; }
        .share-btn { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.375rem; border: 1px solid #d1d5db; background: #fff; color: #374151; text-decoration: none; font-size: 0.875rem; cursor: pointer; }
        .share-btn:hover { background: #f3f4f6; }
        .share-btn.x { background: #000; color: #fff; border-color: #000; }
        .share-btn.x:hover { background: #333; }
        .share-btn.line { background: #06c755; color: #fff; border-color: #06c755; }
        .share-btn.line:hover { background: #05b34c; }

        .manual-badge { display: inline-block; padding: 0.0625rem 0.4rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 700; background: #dbeafe; color: #1d4ed8; margin-left: auto; flex: none; }
        .card .delete-btn { display: none; position: absolute; top: 0.25rem; right: 0.25rem; width: 1.25rem; height: 1.25rem; border-radius: 50%; border: none; background: #ef4444; color: #fff; font-size: 0.625rem; cursor: pointer; line-height: 1; padding: 0; }
        .card:hover .delete-btn { display: flex; align-items: center; justify-content: center; }
        .card { position: relative; }


        .site-footer { margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; font-size: 0.75rem; color: #6b7280; }
        .site-footer nav { display: flex; gap: 1rem; flex: none; }
        .site-footer a { color: #6b7280; text-decoration: none; }
        .site-footer a:hover { color: #111827; text-decoration: underline; }

        .board-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .board-toolbar .nav { display: inline-flex; gap: 0.25rem; }
        .board-toolbar .nav button { padding: 0.375rem 0.75rem; font-size: 0.875rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: #fff; color: #374151; cursor: pointer; }
        .board-toolbar .nav button:hover { background: #f3f4f6; }
        .board-toolbar .range { font-weight: 600; color: #111827; }

        .board { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.5rem; padding-bottom: 0.5rem; }
        @media (max-width: 767px) { .board { grid-template-columns: 1fr; } .day-col { min-height: 0; } }
        .day-col { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; display: flex; flex-direction: column; min-height: 12rem; min-width: 0; }
        .day-col.is-today { border-color: #111827; box-shadow: 0 0 0 1px #111827 inset; }
        .day-col.is-past { opacity: 0.7; }
        .day-head { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: baseline; gap: 0.375rem; }
        .day-head .d { font-size: 1.125rem; font-weight: 700; color: #111827; }
        .day-head .w { font-size: 0.8125rem; color: #6b7280; }
        .day-head .w.sun { color: #dc2626; }
        .day-head .w.sat { color: #2563eb; }
        .day-head .today-tag { margin-left: auto; font-size: 0.625rem; font-weight: 700; color: #fff; background: #111827; padding: 0.125rem 0.5rem; border-radius: 9999px; }
        .day-body { padding: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .day-body .empty { color: #9ca3af; font-size: 0.8125rem; text-align: center; padding: 1rem 0; }

        .card { display: block; min-width: 0; text-decoration: none; color: inherit; border: 1px solid #e5e7eb; border-left: 4px solid #9ca3af; border-radius: 0.375rem; padding: 0.5rem; background: #fff; }
        .card:hover { background: #f9fafb; border-color: #d1d5db; }
        .card.is-done { opacity: 0.55; }
        /* Manually entered schedules: dashed, slightly muted, so they read as "unofficial". */
        .card.is-manual { border-style: dashed; border-width: 1px 1px 1px 4px; border-color: #9ca3af; background: #eef0f3; }
        .card.is-manual:hover { background: #e3e6ea; }
        .card.is-manual .card-title { color: #4b5563; }
        .card.is-manual .card-meta .time { color: #374151; }
        .fc-ev.is-manual { outline: 1px dashed #9ca3af; outline-offset: -1px; border-radius: 0.25rem; opacity: 0.85; }
        .card-head { display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.3rem; min-width: 0; }
        .avatar { width: 1.75rem; height: 1.75rem; border-radius: 50%; object-fit: cover; flex: none; background: #e5e7eb; }
        .avatar-fallback { width: 1.75rem; height: 1.75rem; border-radius: 50%; flex: none; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8125rem; }
        .card-meta { min-width: 0; display: flex; flex-direction: column; flex: 1 1 auto; }
        .card-meta .time { font-weight: 700; font-size: 0.875rem; color: #111827; line-height: 1.2; }
        .card-meta .ch { font-size: 0.6875rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-title { font-size: 0.78rem; line-height: 1.35; color: #1f2937; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; }
        .badge { display: inline-block; padding: 0.0625rem 0.4rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 700; margin-left: auto; flex: none; }
        .badge.live { background: #dc2626; color: #fff; }
        .badge.done { background: #e5e7eb; color: #6b7280; }
        .badge.members { background: #f3e8ff; color: #6b21a8; margin-left: 0.25rem; }
        .card-head .badge + .badge { margin-left: 0.25rem; }

        .fc-event { cursor: pointer; }
        .fc-ev { display: flex; align-items: center; gap: 0.25rem; overflow: hidden; padding: 0 0.125rem; }
        .fc-ev img, .fc-ev .fc-ev-dot { width: 1rem; height: 1rem; border-radius: 50%; flex: none; object-fit: cover; }
        .fc-ev .fc-ev-time { font-weight: 700; font-size: 0.7rem; color: #111827; flex: none; }
        .fc-ev .fc-ev-title { font-size: 0.7rem; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fc-ev.is-done { opacity: 0.55; }

        .tooltip { position: absolute; z-index: 50; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); pointer-events: none; max-width: 320px; font-size: 0.875rem; }
        .tooltip .channel-name { font-weight: 600; margin-bottom: 0.25rem; }
        .tooltip .stream-title { color: #374151; overflow-wrap: anywhere; }
        .tooltip .stream-time { color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; }

        .add-btn { display: flex; align-items: center; justify-content: center; width: 100%; padding: 0.25rem 0; margin-top: auto; border: 1px dashed #d1d5db; border-radius: 0.375rem; background: transparent; color: #9ca3af; font-size: 1rem; cursor: pointer; transition: background 0.1s, color 0.1s; }
        .add-btn:hover { background: #f3f4f6; color: #374151; border-color: #9ca3af; }

        .modal-overlay { position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .modal { background: #fff; border-radius: 0.75rem; box-shadow: 0 20px 60px rgb(0 0 0 / 0.15); width: 100%; max-width: 24rem; padding: 1.5rem; }
        .modal h3 { font-size: 1rem; font-weight: 700; color: #111827; margin: 0 0 1rem; }
        .modal .modal-field { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.75rem; }
        .modal .modal-field label { font-size: 0.75rem; font-weight: 600; color: #6b7280; }
        .modal .modal-field input, .modal .modal-field select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
        .modal .modal-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; }
        .modal .modal-actions button { padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; cursor: pointer; }
        .modal .btn-cancel { background: #fff; border: 1px solid #d1d5db; color: #374151; }
        .modal .btn-cancel:hover { background: #f3f4f6; }
        .modal .btn-submit { background: #111827; border: none; color: #fff; }
        .modal .btn-submit:hover { background: #374151; }
        .modal .modal-error { color: #dc2626; font-size: 0.75rem; margin-top: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-screen-2xl mx-auto px-4 py-8">
        <header class="page-head">
            <div class="page-head-main">
                <div class="title-row">
                    @if ($group->parent)
                        <a href="{{ url('/' . $group->parent->path) }}" class="back-link">←</a>
                    @else
                        <a href="{{ url('/') }}" class="back-link">←</a>
                    @endif
                    <h1>{{ $group->name }}</h1>
                </div>
            </div>
            <div class="page-head-actions">
                <div class="view-toggle" role="tablist">
                    <button type="button" data-view="board" class="is-active">週ボード</button>
                    <button type="button" data-view="month">月</button>
                </div>
                <div class="share-buttons">
                    <a class="share-btn x"
                       href="https://x.com/intent/tweet?url={{ urlencode($pageUrl) }}&text={{ urlencode($pageTitle) }}"
                       target="_blank" rel="noopener noreferrer" title="Xで共有">𝕏</a>
                    <a class="share-btn line"
                       href="https://social-plugins.line.me/lineit/share?url={{ urlencode($pageUrl) }}"
                       target="_blank" rel="noopener noreferrer" title="LINEで共有">L</a>
                    <button type="button" class="share-btn" id="copy-url-btn" title="URLをコピー">🔗</button>
                </div>
                @guest
                    <a href="{{ route('auth.google') }}" class="admin-link" data-cc-login>ログイン</a>
                @else
                    @if (Auth::user()->is_admin)
                        <a href="{{ url('/admin/channels') }}" class="admin-link">管理画面</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="admin-link" style="border:none;background:none;cursor:pointer;">ログアウト</button>
                    </form>
                @endguest
            </div>
        </header>

        @if ($children->isNotEmpty())
            @if ($children->isNotEmpty())
                <div id="subgroup-toggles" class="subgroup-toggles" role="group" aria-label="サブグループで絞り込み">
                    @foreach ($children as $child)
                        <button type="button" data-group-id="{{ $child->id }}">{{ $child->name }}</button>
                    @endforeach
                    <span class="subgroup-hint" id="subgroup-hint" aria-live="polite">クリックすると、そのグループだけに絞り込みます</span>
                </div>
            @endif
        @endif

        <div class="filter-section">
            {{-- Rendered collapsed so the list does not flash open before the script applies the saved state. --}}
            <div class="filter-head">
                <button type="button" id="filter-toggle" class="filter-toggle" aria-expanded="false" aria-controls="channel-filter">
                    <span class="arrow collapsed" id="filter-arrow">▼</span>
                    チャンネル
                </button>
                @auth
                    {{-- Filled by the script once the group's channels are known. --}}
                    <span id="x-group-search" class="x-group-search" hidden></span>
                @endauth
            </div>
            <div id="channel-filter" class="channel-filter" hidden></div>
        </div>

        <section id="board-view">
            <div class="board-toolbar">
                <div class="nav">
                    <button type="button" id="board-prev" title="1週間戻る">« 前の週</button>
                    <button type="button" id="board-prev-day" title="1日戻る">‹ 前日</button>
                    <button type="button" id="board-today" title="今週（月曜始まり）へ">今日</button>
                    <button type="button" id="board-next-day" title="1日進む">翌日 ›</button>
                    <button type="button" id="board-next" title="1週間進む">次の週 »</button>
                </div>
                <div class="range" id="board-range"></div>
            </div>
            <div id="board" class="board"></div>
        </section>

        <section id="month-view" hidden>
            <div id="calendar"></div>
        </section>

        <footer class="site-footer">
            <span>配信情報は YouTube Data API を利用して取得しています。各配信・チャンネルの権利はそれぞれの運営者および YouTube に帰属します。</span>
            <nav>
                <a href="{{ url('/terms') }}">利用規約</a>
                <a href="{{ url('/privacy') }}">プライバシーポリシー</a>
                <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener noreferrer">YouTube 利用規約</a>
                <a href="#" data-cc-reset>Cookie 設定</a>
            </nav>
        </footer>
    </div>

    <div id="tooltip" class="tooltip" hidden></div>

    @auth
    <div id="schedule-modal" class="modal-overlay" hidden>
        <div class="modal">
            <h3>予定を登録 — <span id="modal-date-label"></span></h3>
            <div class="modal-field">
                <label for="modal-channel">チャンネル</label>
                <select id="modal-channel"></select>
            </div>
            <div class="modal-field">
                <label for="modal-title">タイトル</label>
                <input type="text" id="modal-title" placeholder="配信タイトル" maxlength="255">
            </div>
            <div class="modal-field">
                <label class="modal-check">
                    <input type="checkbox" id="modal-all-day" checked>
                    時間未定（その日のどこかで配信）
                </label>
                <span class="modal-hint-text">時間が分かっている場合だけチェックを外して入力してください。公式の配信枠が立てば、この予定は自動的に消えます。</span>
            </div>
            <div class="modal-field" id="modal-time-field" hidden>
                <label for="modal-time">時間</label>
                <input type="time" id="modal-time" value="20:00">
            </div>
            <div class="modal-field">
                <label for="modal-source-url">情報元URL（任意）</label>
                <input type="url" id="modal-source-url" placeholder="https://x.com/... 告知ツイートなど" maxlength="2048">
                <p class="modal-hint">
                    <a id="modal-x-search" href="#" target="_blank" rel="noopener noreferrer" hidden>𝕏 このチャンネルの告知を X で探す ↗</a>
                    <span id="modal-preview-status" hidden></span>
                </p>
            </div>
            <p id="modal-error" class="modal-error" hidden></p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="modal-cancel">キャンセル</button>
                <button type="button" class="btn-submit" id="modal-submit">登録</button>
            </div>
        </div>
    </div>
    @endauth

    <x-cookie-consent />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/ja.global.min.js"></script>
    <script>
    var GROUP_SLUG = @json($group?->path, JSON_UNESCAPED_SLASHES);
    var CHILD_GROUPS = @json($children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values());
    var CHILD_CHANNEL_MAP = @json($childChannelMap);
    var X_SEARCH_KEYWORDS = @json(\App\Support\XSearchKeywords::global());

    // "from:handle (global kws OR channel kws ...)" on X, newest first.
    function xSearchUrl(handle, extraKeywords) {
        var all = X_SEARCH_KEYWORDS.concat(extraKeywords || []).filter(function (k, i, arr) { return k && arr.indexOf(k) === i; });
        var terms = all.join(' OR ');
        return 'https://x.com/search?q=' + encodeURIComponent('from:' + handle + (terms ? ' (' + terms + ')' : '')) + '&f=live';
    }

    // "(from:a OR from:b ...) (kws ...)" for several accounts at once. X caps a
    // query around 500 characters, so long rosters are split into several links.
    var X_QUERY_MAX = 480;
    function xGroupSearchUrls(handles, extraKeywords) {
        var all = X_SEARCH_KEYWORDS.concat(extraKeywords || []).filter(function (k, i, arr) { return k && arr.indexOf(k) === i; });
        var terms = all.length ? ' (' + all.join(' OR ') + ')' : '';
        var urls = [];
        var batch = [];
        function flush() {
            if (!batch.length) return;
            var q = '(' + batch.map(function (h) { return 'from:' + h; }).join(' OR ') + ')' + terms;
            urls.push('https://x.com/search?q=' + encodeURIComponent(q) + '&f=live');
            batch = [];
        }
        handles.forEach(function (h) {
            var candidate = batch.concat([h]).map(function (x) { return 'from:' + x; }).join(' OR ').length + 2 + terms.length;
            if (batch.length && candidate > X_QUERY_MAX) flush();
            batch.push(h);
        });
        flush();
        return urls;
    }
    var IS_LOGGED_IN = @json(Auth::check());
    var CSRF_TOKEN = @json(csrf_token());
    var CURRENT_USER_ID = @json(Auth::id());

    function apiUrl(path, params) {
        var parts = Object.keys(params).map(function (k) {
            return k + '=' + encodeURIComponent(params[k]);
        });
        if (GROUP_SLUG) {
            parts.push('group=' + encodeURIComponent(GROUP_SLUG));
        }
        return path + '?' + parts.join('&');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
        var BOARD_DAYS = 7;

        var boardEl = document.getElementById('board');
        var rangeEl = document.getElementById('board-range');
        var calendarEl = document.getElementById('calendar');
        var tooltipEl = document.getElementById('tooltip');
        var filterEl = document.getElementById('channel-filter');
        var boardView = document.getElementById('board-view');
        var monthView = document.getElementById('month-view');
        var toggleButtons = document.querySelectorAll('.view-toggle button');
        var filterToggleBtn = document.getElementById('filter-toggle');
        var filterArrow = document.getElementById('filter-arrow');

        var hiddenChannels = {};
        // The board opens on the Monday of the current week; day-step buttons may then
        // shift it off that alignment, and "今日" snaps back.
        var boardStart = mondayOf(new Date());
        var highlightEventId = null;  // event id to flash after the next render (newly added schedule)
        var channelList = [];         // active channels from /api/channels, used by the filter and the schedule modal
        // Collapsed by default; an explicit choice (this browser, or server prefs for
        // logged-in users applied later) wins.
        var filterCollapsed = true;
        try {
            var savedFilterState = localStorage.getItem('cc.filterCollapsed');
            if (savedFilterState !== null) { filterCollapsed = savedFilterState === '1'; }
        } catch (e) {}
        filterEl.hidden = filterCollapsed;
        filterArrow.classList.toggle('collapsed', filterCollapsed);
        filterToggleBtn.addEventListener('click', function () {
            filterCollapsed = !filterCollapsed;
            filterEl.hidden = filterCollapsed;
            filterArrow.classList.toggle('collapsed', filterCollapsed);
            filterToggleBtn.setAttribute('aria-expanded', String(!filterCollapsed));
            try { localStorage.setItem('cc.filterCollapsed', filterCollapsed ? '1' : '0'); } catch (e) {}
            syncPrefsToServer();
        });
        var boardEvents = [];
        var calendar = null;

        function startOfDay(d) {
            return new Date(d.getFullYear(), d.getMonth(), d.getDate());
        }
        function addDays(d, n) {
            return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
        }
        function mondayOf(d) {
            var day = startOfDay(d);
            return addDays(day, -((day.getDay() + 6) % 7));  // getDay(): 0 = Sunday
        }
        function dateKey(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        function fmtTime(d) {
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }
        function fmtShort(d) {
            return (d.getMonth() + 1) + '/' + d.getDate() + '(' + WEEKDAYS[d.getDay()] + ')';
        }
        var activeSubgroups = {};
        CHILD_GROUPS.forEach(function (g) { activeSubgroups[g.id] = true; });
        var hasSubgroups = CHILD_GROUPS.length > 0;

        function isVisibleBySubgroup(channelId) {
            if (!hasSubgroups) return true;
            // All subgroups active = no filter: also show channels attached directly to this group.
            if (CHILD_GROUPS.every(function (g) { return !!activeSubgroups[g.id]; })) return true;
            for (var gid in activeSubgroups) {
                if (!activeSubgroups[gid]) continue;
                var chs = CHILD_CHANNEL_MAP[gid] || [];
                if (chs.indexOf(channelId) !== -1) return true;
            }
            return false;
        }

        function isVisible(ev) {
            var chId = ev.extendedProps.channel_id;
            return !hiddenChannels[chId] && isVisibleBySubgroup(chId);
        }

        function avatarNode(props, color, size) {
            if (props.channel_thumbnail_url) {
                var img = document.createElement('img');
                img.className = size;
                img.src = props.channel_thumbnail_url;
                img.alt = '';
                img.loading = 'lazy';
                return img;
            }
            var span = document.createElement('span');
            span.className = size === 'avatar' ? 'avatar-fallback' : 'fc-ev-dot';
            span.style.backgroundColor = color || '#9ca3af';
            if (size === 'avatar') {
                span.textContent = (props.channel_name || '?').charAt(0);
            }
            return span;
        }

        function buildCard(ev) {
            var props = ev.extendedProps;
            var start = new Date(ev.start);
            var a = document.createElement('a');
            a.className = 'card'
                + (props.status === 'completed' ? ' is-done' : '')
                + (props.status === 'manual' ? ' is-manual' : '');
            a.dataset.eventId = String(ev.id);
            a.href = ev.url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.title = props.channel_name + ' ' + fmtTime(start) + '\n' + ev.title;
            a.style.borderLeftColor = ev.color || '#9ca3af';

            var head = document.createElement('div');
            head.className = 'card-head';
            head.appendChild(avatarNode(props, ev.color, 'avatar'));

            var meta = document.createElement('div');
            meta.className = 'card-meta';
            var time = document.createElement('span');
            time.className = 'time' + (props.is_all_day ? ' all-day' : '');
            time.textContent = props.is_all_day ? '時間未定' : fmtTime(start);
            var ch = document.createElement('span');
            ch.className = 'ch';
            ch.textContent = props.channel_name;
            meta.appendChild(time);
            meta.appendChild(ch);
            head.appendChild(meta);

            if (props.status === 'live') {
                var live = document.createElement('span');
                live.className = 'badge live';
                live.textContent = 'LIVE';
                head.appendChild(live);
            } else if (props.status === 'completed') {
                var done = document.createElement('span');
                done.className = 'badge done';
                done.textContent = '終了';
                head.appendChild(done);
            } else if (props.status === 'manual') {
                var manual = document.createElement('span');
                manual.className = 'manual-badge';
                manual.textContent = '手動';
                head.appendChild(manual);
            }
            if (props.is_members_only) {
                var members = document.createElement('span');
                members.className = 'badge members';
                members.textContent = '🔒 メン限';
                members.title = 'メンバー限定配信';
                head.appendChild(members);
            }

            var title = document.createElement('div');
            title.className = 'card-title';
            title.textContent = ev.title;

            a.appendChild(head);
            a.appendChild(title);

            if (props.status === 'manual') {
                if (props.source_url) {
                    // A manual entry has no YouTube URL; link the card to its source instead.
                    a.href = props.source_url;
                    var source = document.createElement('div');
                    source.className = 'card-source';
                    source.textContent = '情報元を見る ↗';
                    a.appendChild(source);
                } else if (!ev.url) {
                    a.removeAttribute('href');
                    a.style.cursor = 'default';
                }
            }

            if (props.status === 'manual' && IS_LOGGED_IN && props.manual_schedule_id) {
                var delBtn = document.createElement('button');
                delBtn.className = 'delete-btn';
                delBtn.textContent = '×';
                delBtn.title = '削除';
                delBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!confirm('この予定を削除しますか？')) return;
                    fetch('/api/manual-schedules/' + props.manual_schedule_id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                    }).then(function (res) {
                        if (res.ok) { loadBoard(); if (calendar) calendar.refetchEvents(); }
                        else { res.json().then(function (d) { alert(d.message || '削除に失敗しました。'); }).catch(function () { alert('削除に失敗しました。'); }); }
                    }).catch(function () { alert('削除に失敗しました。'); });
                });
                a.appendChild(delBtn);
            }

            return a;
        }

        // After creating a manual schedule: jump the board to its week if needed,
        // re-render, then flash the new card so it is easy to spot.
        function focusNewSchedule(created) {
            highlightEventId = 'ms_' + created.id;
            var when = startOfDay(new Date(created.scheduled_at));
            var windowEnd = addDays(boardStart, BOARD_DAYS);
            if (when < boardStart || when >= windowEnd) {
                boardStart = mondayOf(when);
            }
            loadBoard();
            if (calendar) calendar.refetchEvents();
        }

        function flashHighlightedCard() {
            if (!highlightEventId) return;
            var el = boardEl.querySelector('[data-event-id="' + highlightEventId + '"]');
            if (!el) return;
            el.classList.add('is-new');
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            setTimeout(function () { el.classList.remove('is-new'); }, 4000);
            highlightEventId = null;
        }

        function renderBoard() {
            boardEl.textContent = '';
            var todayKey = dateKey(new Date());
            var byDay = {};
            boardEvents.filter(isVisible).forEach(function (ev) {
                var key = dateKey(new Date(ev.start));
                (byDay[key] = byDay[key] || []).push(ev);
            });

            for (var i = 0; i < BOARD_DAYS; i++) {
                var day = addDays(boardStart, i);
                var key = dateKey(day);
                var col = document.createElement('div');
                col.className = 'day-col' + (key === todayKey ? ' is-today' : '') + (key < todayKey ? ' is-past' : '');

                var head = document.createElement('div');
                head.className = 'day-head';
                var d = document.createElement('span');
                d.className = 'd';
                d.textContent = (day.getMonth() + 1) + '/' + day.getDate();
                var w = document.createElement('span');
                w.className = 'w' + (day.getDay() === 0 ? ' sun' : day.getDay() === 6 ? ' sat' : '');
                w.textContent = WEEKDAYS[day.getDay()];
                head.appendChild(d);
                head.appendChild(w);
                if (key === todayKey) {
                    var tag = document.createElement('span');
                    tag.className = 'today-tag';
                    tag.textContent = '今日';
                    head.appendChild(tag);
                }
                col.appendChild(head);

                var body = document.createElement('div');
                body.className = 'day-body';
                var list = (byDay[key] || []).slice().sort(function (a, b) {
                    // All-day (time unknown) entries lead the day, then chronological.
                    var aAll = a.extendedProps.is_all_day ? 0 : 1;
                    var bAll = b.extendedProps.is_all_day ? 0 : 1;
                    if (aAll !== bAll) return aAll - bAll;
                    return new Date(a.start) - new Date(b.start);
                });
                if (list.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'empty';
                    empty.textContent = '予定なし';
                    body.appendChild(empty);
                } else {
                    list.forEach(function (ev) { body.appendChild(buildCard(ev)); });
                }
                if (IS_LOGGED_IN) {
                    var addBtn = document.createElement('button');
                    addBtn.type = 'button';
                    addBtn.className = 'add-btn';
                    addBtn.textContent = '+';
                    addBtn.title = '予定を追加';
                    addBtn.dataset.date = key;
                    addBtn.addEventListener('click', function () { openModal(this.dataset.date); });
                    body.appendChild(addBtn);
                }
                col.appendChild(body);
                boardEl.appendChild(col);
            }

            rangeEl.textContent = fmtShort(boardStart) + ' 〜 ' + fmtShort(addDays(boardStart, BOARD_DAYS - 1));
            flashHighlightedCard();
        }

        function loadBoard() {
            var end = addDays(boardStart, BOARD_DAYS);
            var params = { start: boardStart.toISOString(), end: end.toISOString() };
            Promise.all([
                fetch(apiUrl('/api/streams', params), { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : []; }),
                fetch(apiUrl('/api/manual-schedules', params), { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : []; }),
            ]).then(function (results) {
                boardEvents = results[0].concat(results[1]);
                renderBoard();
            }).catch(function () { boardEvents = []; renderBoard(); });
        }

        document.getElementById('board-prev').addEventListener('click', function () {
            boardStart = addDays(boardStart, -BOARD_DAYS);
            loadBoard();
        });
        document.getElementById('board-next').addEventListener('click', function () {
            boardStart = addDays(boardStart, BOARD_DAYS);
            loadBoard();
        });
        document.getElementById('board-prev-day').addEventListener('click', function () {
            boardStart = addDays(boardStart, -1);
            loadBoard();
        });
        document.getElementById('board-next-day').addEventListener('click', function () {
            boardStart = addDays(boardStart, 1);
            loadBoard();
        });
        document.getElementById('board-today').addEventListener('click', function () {
            boardStart = mondayOf(new Date());
            loadBoard();
        });

        function ensureCalendar() {
            if (calendar) { return; }
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                dayMaxEvents: false,
                displayEventTime: false,
                events: function (info, successCallback, failureCallback) {
                    var p = { start: info.startStr, end: info.endStr };
                    Promise.all([
                        fetch(apiUrl('/api/streams', p), { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : []; }),
                        fetch(apiUrl('/api/manual-schedules', p), { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : []; }),
                    ]).then(function (results) {
                        successCallback(results[0].concat(results[1]).filter(isVisible));
                    }).catch(failureCallback);
                },
                eventDidMount: function (info) {
                    if (highlightEventId && String(info.event.id) === highlightEventId) {
                        info.el.classList.add('is-new');
                        setTimeout(function () { info.el.classList.remove('is-new'); }, 4000);
                    }
                },
                eventContent: function (arg) {
                    var props = arg.event.extendedProps;
                    var wrap = document.createElement('div');
                    wrap.className = 'fc-ev'
                        + (props.status === 'completed' ? ' is-done' : '')
                        + (props.status === 'manual' ? ' is-manual' : '');
                    wrap.appendChild(avatarNode(props, arg.event.backgroundColor || arg.event.borderColor, 'fc-ev-img'));
                    var t = document.createElement('span');
                    t.className = 'fc-ev-time';
                    t.textContent = props.status === 'live' ? 'LIVE' : (props.is_all_day ? '未定' : fmtTime(arg.event.start));
                    if (props.status === 'live') { t.style.color = '#dc2626'; }
                    var ti = document.createElement('span');
                    ti.className = 'fc-ev-title';
                    ti.textContent = (props.is_members_only ? '🔒 ' : '') + arg.event.title;
                    wrap.appendChild(t);
                    wrap.appendChild(ti);
                    return { domNodes: [wrap] };
                },
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.open(info.event.url, '_blank', 'noopener,noreferrer');
                    }
                },
                eventMouseEnter: function (info) {
                    var props = info.event.extendedProps;
                    tooltipEl.textContent = '';
                    var name = document.createElement('div');
                    name.className = 'channel-name';
                    name.textContent = props.channel_name;
                    var title = document.createElement('div');
                    title.className = 'stream-title';
                    title.textContent = info.event.title;
                    var time = document.createElement('div');
                    time.className = 'stream-time';
                    time.textContent = new Date(info.event.start).toLocaleString('ja-JP') + (props.status === 'live' ? '  LIVE' : props.status === 'completed' ? '  終了' : '');
                    tooltipEl.appendChild(name);
                    tooltipEl.appendChild(title);
                    tooltipEl.appendChild(time);
                    tooltipEl.hidden = false;
                    var rect = info.el.getBoundingClientRect();
                    tooltipEl.style.top = (rect.bottom + window.scrollY + 8) + 'px';
                    tooltipEl.style.left = (rect.left + window.scrollX) + 'px';
                },
                eventMouseLeave: function () { tooltipEl.hidden = true; },
                height: 'auto',
            });
            calendar.render();
        }

        function setView(view) {
            var isBoard = view !== 'month';
            boardView.hidden = !isBoard;
            monthView.hidden = isBoard;
            toggleButtons.forEach(function (b) { b.classList.toggle('is-active', b.dataset.view === (isBoard ? 'board' : 'month')); });
            if (!isBoard) {
                ensureCalendar();
                calendar.updateSize();
            }
            try { localStorage.setItem('cc.view', isBoard ? 'board' : 'month'); } catch (e) {}
        }
        toggleButtons.forEach(function (b) {
            b.addEventListener('click', function () { setView(b.dataset.view); syncPrefsToServer(); });
        });

        // Toggle buttons are rendered server-side; wire them up here.
        var subgroupContainer = document.getElementById('subgroup-toggles');
        if (subgroupContainer) {
            function refreshSubgroupUI() {
                renderBoard();
                if (calendar) { calendar.refetchEvents(); }
            }
            var subgroupButtons = Array.from(subgroupContainer.querySelectorAll('button[data-group-id]'));
            var subgroupHint = document.getElementById('subgroup-hint');
            function syncSubgroupButtons() {
                subgroupButtons.forEach(function (b) {
                    b.classList.toggle('is-active', !!activeSubgroups[Number(b.dataset.groupId)]);
                });
                if (subgroupHint) {
                    subgroupHint.textContent = allSubgroupsActive()
                        ? 'クリックすると、そのグループだけに絞り込みます'
                        : 'クリックで追加・解除。すべて外すと全表示に戻ります';
                }
            }
            function allSubgroupsActive() {
                return CHILD_GROUPS.every(function (g) { return !!activeSubgroups[g.id]; });
            }
            function noSubgroupActive() {
                return CHILD_GROUPS.every(function (g) { return !activeSubgroups[g.id]; });
            }
            subgroupButtons.forEach(function (btn) {
                var id = Number(btn.dataset.groupId);
                btn.addEventListener('click', function () {
                    if (allSubgroupsActive()) {
                        // "Everything shown" is the neutral state: the first click narrows to just this group.
                        CHILD_GROUPS.forEach(function (g) { activeSubgroups[g.id] = false; });
                        activeSubgroups[id] = true;
                    } else {
                        activeSubgroups[id] = !activeSubgroups[id];
                        // Deselecting the last group would show nothing; fall back to everything.
                        if (noSubgroupActive()) {
                            CHILD_GROUPS.forEach(function (g) { activeSubgroups[g.id] = true; });
                        }
                    }
                    syncSubgroupButtons();
                    refreshSubgroupUI();
                });
            });
            syncSubgroupButtons();
        }

        function saveHiddenChannels() {
            try { localStorage.setItem('cc.hiddenChannels', JSON.stringify(Object.keys(hiddenChannels))); } catch (e) {}
        }
        function loadHiddenChannels() {
            try {
                var saved = localStorage.getItem('cc.hiddenChannels');
                if (saved) { JSON.parse(saved).forEach(function (id) { hiddenChannels[Number(id)] = true; }); }
            } catch (e) {}
        }
        loadHiddenChannels();

        fetch(apiUrl('/api/channels', {}), { headers: { Accept: 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (channels) {
                channels.forEach(function (ch) {
                    var label = document.createElement('label');
                    label.dataset.channelId = ch.id;
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = !hiddenChannels[ch.id];
                    checkbox.addEventListener('change', function () {
                        if (this.checked) { delete hiddenChannels[ch.id]; } else { hiddenChannels[ch.id] = true; }
                        saveHiddenChannels();
                        syncPrefsToServer();
                        renderBoard();
                        if (calendar) { calendar.refetchEvents(); }
                    });
                    label.appendChild(checkbox);
                    if (ch.thumbnail_url) {
                        var img = document.createElement('img');
                        img.src = ch.thumbnail_url;
                        img.alt = '';
                        label.appendChild(img);
                    } else {
                        var dot = document.createElement('span');
                        dot.style.cssText = 'display:inline-block;width:.75rem;height:.75rem;border-radius:50%;background:' + ch.color;
                        label.appendChild(dot);
                    }
                    label.appendChild(document.createTextNode(ch.name));
                    if (IS_LOGGED_IN && ch.x_handle) {
                        var xLink = document.createElement('a');
                        xLink.className = 'x-link';
                        xLink.href = xSearchUrl(ch.x_handle, ch.x_search_keywords);
                        xLink.target = '_blank';
                        xLink.rel = 'noopener noreferrer';
                        xLink.title = 'X で ' + ch.name + ' の告知を探す';
                        xLink.textContent = '𝕏';
                        xLink.addEventListener('click', function (e) { e.stopPropagation(); });
                        label.appendChild(xLink);
                    }
                    filterEl.appendChild(label);
                });
                channelList = channels;
                renderGroupXSearch();
                initFromPrefs();
            }).catch(function () { initFromPrefs(); });

        // One X search covering every channel of this page that has an X account.
        function renderGroupXSearch() {
            var box = document.getElementById('x-group-search');
            if (!box) return;
            var withX = channelList.filter(function (c) { return c.x_handle; });
            if (!withX.length) { box.hidden = true; return; }
            var extras = [];
            withX.forEach(function (c) { (c.x_search_keywords || []).forEach(function (k) { if (extras.indexOf(k) === -1) extras.push(k); }); });
            var urls = xGroupSearchUrls(withX.map(function (c) { return c.x_handle; }), extras);
            box.textContent = '';
            urls.forEach(function (u, i) {
                var a = document.createElement('a');
                a.href = u;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.textContent = urls.length > 1
                    ? '𝕏 グループの告知を探す (' + (i + 1) + '/' + urls.length + ')'
                    : '𝕏 グループの告知を探す（' + withX.length + 'アカウント）';
                a.title = '登録済みの X アカウントすべてを対象に、告知キーワードで検索します';
                box.appendChild(a);
            });
            box.hidden = false;
        }

        var modalOverlay = document.getElementById('schedule-modal');
        var modalDate = '';

        // "Find announcements on X" link follows the channel chosen in the modal.
        function updateModalXSearch() {
            var link = document.getElementById('modal-x-search');
            var select = document.getElementById('modal-channel');
            if (!link || !select) return;
            var ch = channelList.find(function (c) { return String(c.id) === String(select.value); });
            if (ch && ch.x_handle) {
                link.href = xSearchUrl(ch.x_handle, ch.x_search_keywords);
                link.hidden = false;
            } else {
                link.hidden = true;
            }
        }
        if (modalOverlay) {
            document.getElementById('modal-channel').addEventListener('change', updateModalXSearch);
            document.getElementById('modal-all-day').addEventListener('change', function () {
                document.getElementById('modal-time-field').hidden = this.checked;
            });

            // Paste a post URL → pick the channel whose X account posted it, then pull
            // the text via oEmbed and offer it as the title.
            var TWEET_URL = /^https?:\/\/(?:www\.|mobile\.)?(?:x\.com|twitter\.com)\/([A-Za-z0-9_]{1,15})\/status\/\d+/i;
            var previewStatus = document.getElementById('modal-preview-status');
            function selectChannelByXHandle(handle) {
                if (!handle) return null;
                var ch = channelList.find(function (c) { return c.x_handle && c.x_handle.toLowerCase() === handle.toLowerCase(); });
                if (!ch) return null;
                var select = document.getElementById('modal-channel');
                if (select.querySelector('option[value="' + ch.id + '"]')) {
                    select.value = String(ch.id);
                    updateModalXSearch();
                }
                return ch;
            }
            document.getElementById('modal-source-url').addEventListener('change', function () {
                var url = this.value.trim();
                var titleEl = document.getElementById('modal-title');
                var match = url.match(TWEET_URL);
                if (!match) return;
                var matched = selectChannelByXHandle(match[1]);
                previewStatus.textContent = '投稿を読み込み中…';
                previewStatus.hidden = false;
                fetch('/api/tweet-preview?url=' + encodeURIComponent(url), { headers: { Accept: 'application/json' } })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (r) {
                        if (!r.ok) { previewStatus.textContent = r.data.message || '投稿を取得できませんでした。'; return; }
                        matched = selectChannelByXHandle(r.data.author_handle) || matched;
                        var notes = [];
                        if (matched) {
                            notes.push('チャンネルを「' + matched.name + '」に設定しました。');
                        } else {
                            notes.push('投稿者 @' + (r.data.author_handle || match[1]) + ' に一致するチャンネルがないため、チャンネルは手動で選んでください。');
                        }
                        var firstLine = (r.data.text || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean)[0] || '';
                        if (firstLine && !titleEl.value.trim()) {
                            titleEl.value = firstLine.slice(0, 255);
                            notes.push('本文をタイトルに入れました。必要なら編集してください。');
                        } else if (r.data.text) {
                            notes.push((r.data.author_name || '') + ': ' + r.data.text.slice(0, 80));
                        }
                        previewStatus.textContent = notes.join(' ');
                    })
                    .catch(function () { previewStatus.textContent = '投稿を取得できませんでした。'; });
            });
        }
        function openModal(dateStr) {
            if (!modalOverlay) return;
            modalDate = dateStr;
            var parts = dateStr.split('-');
            document.getElementById('modal-date-label').textContent = parts[1] + '/' + parts[2];
            document.getElementById('modal-error').hidden = true;
            document.getElementById('modal-title').value = '';
            document.getElementById('modal-source-url').value = '';

            var modalChannel = document.getElementById('modal-channel');
            if (modalChannel.options.length === 0) {
                channelList.forEach(function (ch) {
                    var o = document.createElement('option');
                    o.value = ch.id;
                    o.textContent = ch.name;
                    modalChannel.appendChild(o);
                });
            }
            try {
                var saved = localStorage.getItem('cc.lastChannel');
                if (saved && modalChannel.querySelector('option[value="' + saved + '"]')) {
                    modalChannel.value = saved;
                }
            } catch (e) {}

            updateModalXSearch();
            modalOverlay.hidden = false;
            document.getElementById('modal-title').focus();
        }
        function closeModal() {
            if (modalOverlay) modalOverlay.hidden = true;
        }
        if (modalOverlay) {
            // Close on a genuine backdrop click only: a text-selection drag that starts
            // inside the dialog and ends on the backdrop also fires "click" on the
            // backdrop, so require the press to have started there as well.
            var pressedOnBackdrop = false;
            modalOverlay.addEventListener('mousedown', function (e) {
                pressedOnBackdrop = (e.target === modalOverlay);
            });
            modalOverlay.addEventListener('click', function (e) {
                if (e.target === modalOverlay && pressedOnBackdrop) closeModal();
                pressedOnBackdrop = false;
            });
            document.getElementById('modal-cancel').addEventListener('click', closeModal);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modalOverlay.hidden) closeModal();
            });
            var modalSubmitBtn = document.getElementById('modal-submit');
            modalSubmitBtn.addEventListener('click', function () {
                var errEl = document.getElementById('modal-error');
                errEl.hidden = true;
                var channelId = document.getElementById('modal-channel').value;
                var title = document.getElementById('modal-title').value.trim();
                var allDay = document.getElementById('modal-all-day').checked;
                var time = allDay ? '00:00' : document.getElementById('modal-time').value;
                var sourceUrl = document.getElementById('modal-source-url').value.trim();
                if (!channelId || !title || !time) {
                    errEl.textContent = 'すべての項目を入力してください。';
                    errEl.hidden = false;
                    return;
                }
                modalSubmitBtn.disabled = true;
                try { localStorage.setItem('cc.lastChannel', channelId); } catch (e) {}
                // "YYYY-MM-DDTHH:MM" without an offset is parsed as browser-local time;
                // send the absolute instant so the server does not read it as UTC.
                var datetime = new Date(modalDate + 'T' + time).toISOString();
                fetch('/api/manual-schedules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                    body: JSON.stringify({ channel_id: Number(channelId), title: title, source_url: sourceUrl || null, scheduled_at: datetime, is_all_day: allDay }),
                }).then(function (res) {
                    if (res.ok) {
                        closeModal();
                        return res.json().then(focusNewSchedule);
                    } else {
                        return res.json().then(function (data) {
                            errEl.textContent = data.message || 'エラーが発生しました。';
                            errEl.hidden = false;
                        });
                    }
                }).catch(function () {
                    errEl.textContent = 'エラーが発生しました。';
                    errEl.hidden = false;
                }).finally(function () { modalSubmitBtn.disabled = false; });
            });
        }

        var copyUrlBtn = document.getElementById('copy-url-btn');
        if (copyUrlBtn) {
            copyUrlBtn.addEventListener('click', function () {
                navigator.clipboard.writeText(window.location.href).then(function () {
                    copyUrlBtn.textContent = '✓';
                    setTimeout(function () { copyUrlBtn.textContent = '🔗'; }, 1500);
                });
            });
        }

        function syncPrefsToServer() {
            if (!IS_LOGGED_IN) return;
            var data = {
                hidden_channels: Object.keys(hiddenChannels).map(Number),
                view: document.querySelector('.view-toggle button.is-active')?.dataset.view || 'board',
                filter_collapsed: filterCollapsed,
            };
            fetch('/api/preferences', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, Accept: 'application/json' },
                body: JSON.stringify(data),
            }).catch(function () {});
        }

        function initFromPrefs() {
            if (!IS_LOGGED_IN) {
                var savedView = 'board';
                try { savedView = localStorage.getItem('cc.view') || 'board'; } catch (e) {}
                setView(savedView);
                loadBoard();
                return;
            }
            fetch('/api/preferences', { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN } })
                .then(function (r) { return r.ok ? r.json() : {}; })
                .then(function (prefs) {
                    if (prefs.hidden_channels && prefs.hidden_channels.length) {
                        hiddenChannels = {};
                        prefs.hidden_channels.forEach(function (id) { hiddenChannels[id] = true; });
                        try { localStorage.setItem('cc.hiddenChannels', JSON.stringify(prefs.hidden_channels.map(String))); } catch (e) {}
                        filterEl.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
                            var chId = Number(cb.closest('label')?.dataset?.channelId);
                            if (chId) cb.checked = !hiddenChannels[chId];
                        });
                    }
                    if (prefs.filter_collapsed !== undefined && prefs.filter_collapsed !== null) {
                        filterCollapsed = prefs.filter_collapsed;
                        filterEl.hidden = filterCollapsed;
                        filterArrow.classList.toggle('collapsed', filterCollapsed);
                    }
                    setView(prefs.view || 'board');
                    loadBoard();
                })
                .catch(function () {
                    setView('board');
                    loadBoard();
                });
        }

    });
    </script>
</body>
</html>
