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
    <x-ga4 />
    <script>
    (function(){var c=document.documentElement.classList,t;try{t=localStorage.getItem('cc.theme')}catch(e){}
    if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme:dark)').matches))c.add('dark');})();
    </script>
    <style>
        :root {
            --cc-bg: #f9fafb; --cc-surface: #fff; --cc-surface-hover: #f9fafb; --cc-surface-alt: #f3f4f6;
            --cc-text: #111827; --cc-text-sub: #1f2937; --cc-text-secondary: #374151;
            --cc-text-tertiary: #6b7280; --cc-text-muted: #9ca3af;
            --cc-border: #e5e7eb; --cc-border-light: #d1d5db;
            --cc-active-bg: #111827; --cc-active-text: #fff;
            --cc-manual-bg: #eef0f3; --cc-manual-hover: #e3e6ea; --cc-manual-text: #4b5563;
            --cc-modal-overlay: rgba(0,0,0,0.4); --cc-tweet-bg: #f9fafb; --cc-input-bg: #fff;
            --cc-flash-bg: #fef08a; --cc-flash-ring: #facc15;
            --cc-flash-mid: #fef9c3; --cc-flash-mid-ring: #fde047;
            --cc-flash-end: var(--cc-surface);
        }
        .dark {
            --cc-bg: #0c0c0e; --cc-surface: #1a1a1c; --cc-surface-hover: #222224; --cc-surface-alt: #27272a;
            --cc-text: #f4f4f5; --cc-text-sub: #e4e4e7; --cc-text-secondary: #a1a1aa;
            --cc-text-tertiary: #71717a; --cc-text-muted: #52525b;
            --cc-border: #27272a; --cc-border-light: #3f3f46;
            --cc-active-bg: #f4f4f5; --cc-active-text: #18181b;
            --cc-manual-bg: #1e1e22; --cc-manual-hover: #27272a; --cc-manual-text: #a1a1aa;
            --cc-modal-overlay: rgba(0,0,0,0.6); --cc-tweet-bg: #18181b; --cc-input-bg: #27272a;
            --cc-flash-bg: #854d0e; --cc-flash-ring: #a16207;
            --cc-flash-mid: #713f12; --cc-flash-mid-ring: #854d0e;
            --cc-flash-end: var(--cc-surface);
        }
        /* Class rules below (e.g. .modal-overlay { display:flex }) would otherwise
           outrank the preflight [hidden] rule and keep hidden elements visible. */
        [hidden] { display: none !important; }
        .card-meta .time .src-icon { margin-left: 0.3rem; font-size: 0.75rem; font-weight: 400; vertical-align: 0.05em; opacity: 0.8; }
        .card:hover .src-icon { opacity: 1; }

        @keyframes cc-new-flash {
            0%   { background: var(--cc-flash-bg); box-shadow: 0 0 0 3px var(--cc-flash-ring); }
            60%  { background: var(--cc-flash-mid); box-shadow: 0 0 0 3px var(--cc-flash-mid-ring); }
            100% { background: var(--cc-flash-end); box-shadow: 0 0 0 0 transparent; }
        }
        .card.is-new { animation: cc-new-flash 4s ease-out forwards; }
        .fc-event.is-new { animation: cc-new-flash 4s ease-out forwards; border-radius: 0.25rem; }

        .filter-section { margin-bottom: 1rem; }
        .filter-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .x-group-search { display: inline-flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.8125rem; }
        .x-group-search a { color: var(--cc-text-secondary); text-decoration: none; padding: 0.25rem 0.75rem; border: 1px solid var(--cc-border-light); border-radius: 9999px; background: var(--cc-surface); }
        .x-group-search a:hover { background: var(--cc-surface-alt); color: var(--cc-text); }
        .filter-toggle { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0; font-size: 0.875rem; font-weight: 600; color: var(--cc-text-secondary); background: none; border: none; cursor: pointer; }
        .filter-toggle:hover { color: var(--cc-text); }
        .filter-toggle .arrow { display: inline-block; transition: transform 0.15s; font-size: 0.75rem; }
        .filter-toggle .arrow.collapsed { transform: rotate(-90deg); }
        .channel-filter { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .channel-filter label { display: flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; cursor: pointer; border: 1px solid var(--cc-border); background: var(--cc-surface); }
        .channel-filter label:hover { background-color: var(--cc-surface-alt); }
        .channel-filter img { width: 1.25rem; height: 1.25rem; border-radius: 50%; object-fit: cover; }
        .channel-filter .x-link { margin-left: 0.25rem; font-size: 0.75rem; color: var(--cc-text-tertiary); text-decoration: none; padding: 0 0.2rem; border-radius: 0.25rem; }
        .channel-filter .x-link:hover { color: var(--cc-text); background: var(--cc-border); }
        .modal-hint { margin: 0.375rem 0 0; font-size: 0.75rem; color: var(--cc-text-tertiary); display: flex; flex-direction: column; gap: 0.25rem; }
        .modal-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: var(--cc-text); cursor: pointer; }
        .tweet-preview { margin-top: 0.5rem; padding: 0.625rem 0.75rem; background: var(--cc-tweet-bg); border: 1px solid var(--cc-border); border-radius: 0.375rem; }
        .tweet-preview-author { font-size: 0.75rem; font-weight: 600; color: var(--cc-text-secondary); margin-bottom: 0.25rem; }
        .tweet-preview-text { font-size: 0.8125rem; line-height: 1.5; color: var(--cc-text-sub); white-space: pre-wrap; overflow-wrap: anywhere; max-height: 12rem; overflow-y: auto; user-select: text; cursor: text; }
        .tweet-preview-note { margin-top: 0.375rem; font-size: 0.6875rem; color: var(--cc-text-tertiary); }
        .modal-hint-text { font-size: 0.75rem; color: var(--cc-text-tertiary); }
        .modal-hint a { color: #2563eb; text-decoration: none; }
        .modal-hint a:hover { text-decoration: underline; }

        .view-toggle { display: inline-flex; border: 1px solid var(--cc-border-light); border-radius: 0.5rem; overflow: hidden; background: var(--cc-surface); }
        .view-toggle button { padding: 0.375rem 0.875rem; font-size: 0.875rem; color: var(--cc-text-secondary); background: transparent; border: 0; cursor: pointer; }
        .view-toggle button + button { border-left: 1px solid var(--cc-border-light); }
        .view-toggle button.is-active { background: var(--cc-active-bg); color: var(--cc-active-text); }

        .page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .page-head-main { min-width: 0; }
        .page-head-actions { display: flex; align-items: center; gap: 1rem; }
        .title-row { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }
        .back-link { font-size: 1.25rem; color: var(--cc-text-tertiary); text-decoration: none; line-height: 1; }
        .back-link:hover { color: var(--cc-text); }
        .title-row h1 { font-size: 1.5rem; font-weight: 700; color: var(--cc-text); line-height: 1.2; margin: 0; }
        .subgroup-toggles { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-bottom: 0.75rem; }
        .subgroup-toggles button { padding: 0.3rem 0.75rem; font-size: 0.8125rem; border: 1px solid var(--cc-border-light); border-radius: 9999px; background: var(--cc-surface); color: var(--cc-text-secondary); cursor: pointer; transition: background 0.1s, color 0.1s; }
        .subgroup-toggles button:hover { background: var(--cc-surface-alt); }
        .subgroup-toggles button.is-active { background: var(--cc-active-bg); color: var(--cc-active-text); border-color: var(--cc-active-bg); }
        .subgroup-hint { align-self: center; font-size: 0.75rem; color: var(--cc-text-tertiary); margin-left: 0.25rem; }
        .admin-link { font-size: 0.875rem; color: #2563eb; text-decoration: none; }
        .admin-link:hover { text-decoration: underline; }
        .share-buttons { display: inline-flex; gap: 0.375rem; }
        .share-btn { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.375rem; border: 1px solid var(--cc-border-light); background: var(--cc-surface); color: var(--cc-text-secondary); text-decoration: none; font-size: 0.875rem; cursor: pointer; }
        .share-btn:hover { background: var(--cc-surface-alt); }
        .share-btn.x { background: #000; color: #fff; border-color: #000; }
        .share-btn.x:hover { background: #333; }
        .share-btn.line { background: #06c755; color: #fff; border-color: #06c755; }
        .share-btn.line:hover { background: #05b34c; }

        .card .delete-btn { display: none; position: absolute; top: 0.25rem; right: 0.25rem; width: 1.25rem; height: 1.25rem; border-radius: 50%; border: none; background: #ef4444; color: #fff; font-size: 0.625rem; cursor: pointer; line-height: 1; padding: 0; }
        .card:hover .delete-btn { display: flex; align-items: center; justify-content: center; }
        .card { position: relative; }


        .site-footer { margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid var(--cc-border); display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; font-size: 0.75rem; color: var(--cc-text-tertiary); }
        .site-footer nav { display: flex; gap: 1rem; flex: none; }
        .site-footer a { color: var(--cc-text-tertiary); text-decoration: none; }
        .site-footer a:hover { color: var(--cc-text); text-decoration: underline; }

        .board-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .board-toolbar .nav { display: inline-flex; gap: 0.25rem; }
        .board-toolbar .nav button { padding: 0.375rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--cc-border-light); border-radius: 0.375rem; background: var(--cc-surface); color: var(--cc-text-secondary); cursor: pointer; }
        .board-toolbar .nav button:hover { background: var(--cc-surface-alt); }
        .board-toolbar .range { font-weight: 600; color: var(--cc-text); }

        .board { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.5rem; padding-bottom: 0.5rem; }
        @media (max-width: 767px) { .board { grid-template-columns: 1fr; } .day-col { min-height: 0; } }
        .day-col { background: var(--cc-surface); border: 1px solid var(--cc-border); border-radius: 0.5rem; display: flex; flex-direction: column; min-height: 12rem; min-width: 0; }
        .day-col.is-today { border-color: var(--cc-text); box-shadow: 0 0 0 1px var(--cc-text) inset; }
        .day-col.is-past { opacity: 0.7; }
        .day-head { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--cc-border); display: flex; align-items: baseline; gap: 0.375rem; }
        .day-head .d { font-size: 1.125rem; font-weight: 700; color: var(--cc-text); }
        .day-head .w { font-size: 0.8125rem; color: var(--cc-text-tertiary); }
        .day-head .w.sun { color: #dc2626; }
        .day-head .w.sat { color: #2563eb; }
        .day-head .today-tag { margin-left: auto; font-size: 0.625rem; font-weight: 700; color: var(--cc-active-text); background: var(--cc-active-bg); padding: 0.125rem 0.5rem; border-radius: 9999px; }
        .day-body { padding: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .day-body .empty { color: var(--cc-text-muted); font-size: 0.8125rem; text-align: center; padding: 1rem 0; }

        .card { display: block; min-width: 0; text-decoration: none; color: inherit; border: 1px solid var(--cc-border); border-left: 4px solid #9ca3af; border-radius: 0.375rem; padding: 0.5rem; background: var(--cc-surface); }
        .card:hover { background: var(--cc-surface-hover); border-color: var(--cc-border-light); }
        .card.is-done { opacity: 0.55; }
        /* Manually entered schedules: dashed, slightly muted, so they read as "unofficial". */
        .card.is-manual { border-style: dashed; border-width: 1px 1px 1px 4px; border-color: #9ca3af; background: var(--cc-manual-bg); }
        .card.is-manual:hover { background: var(--cc-manual-hover); }
        .card.is-manual .card-title { color: var(--cc-manual-text); }
        .card.is-manual .card-meta .time { color: var(--cc-text-secondary); }
        .fc-ev.is-manual { outline: 1px dashed #9ca3af; outline-offset: -1px; border-radius: 0.25rem; opacity: 0.85; }
        .card-head { display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.3rem; min-width: 0; }
        .avatar { width: 1.75rem; height: 1.75rem; border-radius: 50%; object-fit: cover; flex: none; background: var(--cc-border); }
        .avatar-fallback { width: 1.75rem; height: 1.75rem; border-radius: 50%; flex: none; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8125rem; }
        .card-meta { min-width: 0; display: flex; flex-direction: column; flex: 1 1 auto; }
        .card-meta .time { font-weight: 700; font-size: 0.875rem; color: var(--cc-text); line-height: 1.2; white-space: nowrap; }
        .card-meta .ch { font-size: 0.6875rem; color: var(--cc-text-tertiary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-title { font-size: 0.78rem; line-height: 1.35; color: var(--cc-text-sub); overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; }
        .badge { display: inline-block; padding: 0.0625rem 0.4rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 700; margin-left: auto; flex: none; }
        .badge.live { background: #dc2626; color: #fff; }
        .badge.done { background: var(--cc-border); color: var(--cc-text-tertiary); }
        .badge.members { background: #f3e8ff; color: #6b21a8; margin-left: 0.25rem; }
        .card-head .badge + .badge { margin-left: 0.25rem; }

        .fc-event { cursor: pointer; }
        .fc-ev { display: flex; align-items: center; gap: 0.25rem; overflow: hidden; padding: 0 0.125rem; }
        .fc-ev img, .fc-ev .fc-ev-dot { width: 1rem; height: 1rem; border-radius: 50%; flex: none; object-fit: cover; }
        .fc-ev .fc-ev-time { font-weight: 700; font-size: 0.7rem; color: var(--cc-text); flex: none; }
        .fc-ev .fc-ev-title { font-size: 0.7rem; color: var(--cc-text-sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fc-ev.is-done { opacity: 0.55; }
        /* Block events (all-day manual entries) sit on the pastel channel colour, not
           on the page surface, so their text must stay dark in both themes. */
        .fc-daygrid-block-event .fc-ev-time { color: #111827; }
        .fc-daygrid-block-event .fc-ev-title { color: #1f2937; }

        .tooltip { position: absolute; z-index: 50; background: var(--cc-surface); border: 1px solid var(--cc-border); border-radius: 0.5rem; padding: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); pointer-events: none; max-width: 320px; font-size: 0.875rem; }
        .tooltip .channel-name { font-weight: 600; margin-bottom: 0.25rem; }
        .tooltip .stream-title { color: var(--cc-text-secondary); overflow-wrap: anywhere; }
        .tooltip .stream-time { color: var(--cc-text-tertiary); font-size: 0.75rem; margin-top: 0.25rem; }

        .add-btn { display: flex; align-items: center; justify-content: center; width: 100%; padding: 0.25rem 0; margin-top: auto; border: 1px dashed var(--cc-border-light); border-radius: 0.375rem; background: transparent; color: var(--cc-text-muted); font-size: 1rem; cursor: pointer; transition: background 0.1s, color 0.1s; }
        .add-btn:hover { background: var(--cc-surface-alt); color: var(--cc-text-secondary); border-color: var(--cc-text-muted); }

        .modal-overlay { position: fixed; inset: 0; z-index: 100; background: var(--cc-modal-overlay); display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .modal { background: var(--cc-surface); border-radius: 0.75rem; box-shadow: 0 20px 60px rgb(0 0 0 / 0.15); width: 100%; max-width: 24rem; padding: 1.5rem; }
        .modal h3 { font-size: 1rem; font-weight: 700; color: var(--cc-text); margin: 0 0 1rem; }
        .modal .modal-field { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 0.75rem; }
        .modal .modal-field label { font-size: 0.75rem; font-weight: 600; color: var(--cc-text-tertiary); }
        /* Not checkboxes: the `background` shorthand would wipe the check-mark image
           and `color` would make the checked fill blend into the surface. */
        .modal .modal-field input:not([type=checkbox]), .modal .modal-field select { padding: 0.5rem; border: 1px solid var(--cc-border-light); border-radius: 0.375rem; font-size: 0.875rem; background: var(--cc-input-bg); color: var(--cc-text); }
        .modal .modal-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; }
        .modal .modal-actions button { padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; cursor: pointer; }
        .modal .btn-cancel { background: var(--cc-surface); border: 1px solid var(--cc-border-light); color: var(--cc-text-secondary); }
        .modal .btn-cancel:hover { background: var(--cc-surface-alt); }
        .modal .btn-submit { background: var(--cc-active-bg); border: none; color: var(--cc-active-text); }
        .modal .btn-submit:hover { background: var(--cc-text-secondary); }
        .modal .modal-error { color: #dc2626; font-size: 0.75rem; margin-top: 0.5rem; }

        .theme-toggle { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.375rem; border: 1px solid var(--cc-border-light); background: var(--cc-surface); color: var(--cc-text-secondary); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }
        .theme-toggle:hover { background: var(--cc-surface-alt); }

        .dark .fc { --fc-border-color: var(--cc-border); --fc-page-bg-color: var(--cc-surface); --fc-neutral-bg-color: var(--cc-surface-alt); --fc-today-bg-color: rgba(255,255,255,0.04); }
        .dark .fc .fc-button { background: var(--cc-surface); border-color: var(--cc-border-light); color: var(--cc-text-secondary); }
        .dark .fc .fc-button:hover { background: var(--cc-surface-alt); }
        .dark .fc .fc-button-active, .dark .fc .fc-button:active { background: var(--cc-active-bg) !important; color: var(--cc-active-text) !important; }
        .dark .fc .fc-col-header-cell-cushion, .dark .fc .fc-daygrid-day-number { color: var(--cc-text); text-decoration: none; }
        .dark .fc .fc-toolbar-title { color: var(--cc-text); }
        .dark .fc .fc-day-today { background: rgba(255,255,255,0.04) !important; }
        .dark .admin-link { color: #60a5fa; }
    </style>
</head>
<body style="background:var(--cc-bg);color:var(--cc-text)">
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
                <button type="button" class="theme-toggle" id="theme-toggle" title="テーマ切り替え">
                    <span id="theme-icon">🌙</span>
                </button>
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
                    <button type="button" id="board-today" title="今日から7日間を表示">今日</button>
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
                {{-- Full post text, selectable, so the relevant part can be copied into the title. --}}
                <div id="modal-tweet-preview" class="tweet-preview" hidden>
                    <div class="tweet-preview-author" id="modal-tweet-author"></div>
                    <div class="tweet-preview-text" id="modal-tweet-text"></div>
                    <div class="tweet-preview-note">必要な部分を選択してコピーし、タイトルに貼り付けてください。</div>
                </div>
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
    (function(){
        var btn = document.getElementById('theme-toggle');
        var icon = document.getElementById('theme-icon');
        function syncIcon() { icon.textContent = document.documentElement.classList.contains('dark') ? '☀️' : '🌙'; }
        syncIcon();
        btn.addEventListener('click', function() {
            var isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('cc.theme', isDark ? 'dark' : 'light'); } catch(e) {}
            syncIcon();
        });
    })();
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
        // The board opens on the Monday of the current week; the nav buttons may then
        // shift it, and "今日" puts today in the leftmost column.
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
            time.className = 'time';
            if (props.status === 'manual') {
                // Manual entries: "手動" stands where the time would be (the exact time is
                // usually approximate anyway); append it when one was given.
                time.textContent = props.is_all_day ? '手動' : '手動 ' + fmtTime(start);
            } else {
                time.textContent = fmtTime(start);
            }
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
                    // A manual entry has no YouTube URL; the whole card links to its source,
                    // signalled by a small link icon next to the 手動 label.
                    a.href = props.source_url;
                    var srcIcon = document.createElement('span');
                    srcIcon.className = 'src-icon';
                    srcIcon.textContent = '🔗';
                    srcIcon.title = '情報元を開く';
                    srcIcon.setAttribute('aria-label', '情報元あり');
                    time.appendChild(srcIcon);
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
                lastLoadedAt = Date.now();
                renderBoard();
            }).catch(function () { boardEvents = []; renderBoard(); });
        }

        // Keep the page current without reloads: the server re-checks streams every
        // 10 minutes (live / ended / new frames), so poll a little more often than
        // that. Skip hidden tabs and open modals; catch up as soon as the tab is back.
        var AUTO_REFRESH_MS = 5 * 60 * 1000;
        var lastLoadedAt = 0;
        function refreshIfDue(force) {
            if (document.hidden) return;
            if (modalOverlay && !modalOverlay.hidden) return;
            if (!force && Date.now() - lastLoadedAt < AUTO_REFRESH_MS) return;
            loadBoard();
            if (calendar && !monthView.hidden) calendar.refetchEvents();
        }
        setInterval(function () { refreshIfDue(false); }, 60 * 1000);
        document.addEventListener('visibilitychange', function () { refreshIfDue(false); });

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
            boardStart = startOfDay(new Date());
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
                    t.textContent = props.status === 'live' ? 'LIVE'
                        : (props.status === 'manual' ? (props.is_all_day ? '手動' : '手動 ' + fmtTime(arg.event.start)) : fmtTime(arg.event.start));
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
            var tweetPreview = document.getElementById('modal-tweet-preview');
            function hideTweetPreview() {
                tweetPreview.hidden = true;
                document.getElementById('modal-tweet-text').textContent = '';
                document.getElementById('modal-tweet-author').textContent = '';
            }
            document.getElementById('modal-source-url').addEventListener('change', function () {
                var url = this.value.trim();
                var match = url.match(TWEET_URL);
                if (!match) { hideTweetPreview(); return; }
                var matched = selectChannelByXHandle(match[1]);
                previewStatus.textContent = '投稿を読み込み中…';
                previewStatus.hidden = false;
                fetch('/api/tweet-preview?url=' + encodeURIComponent(url), { headers: { Accept: 'application/json' } })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (r) {
                        if (!r.ok) { previewStatus.textContent = r.data.message || '投稿を取得できませんでした。'; hideTweetPreview(); return; }
                        matched = selectChannelByXHandle(r.data.author_handle) || matched;
                        previewStatus.textContent = matched
                            ? 'チャンネルを「' + matched.name + '」に設定しました。'
                            : '投稿者 @' + (r.data.author_handle || match[1]) + ' に一致するチャンネルがないため、チャンネルは手動で選んでください。';
                        // Show the whole post; the first line is usually a greeting, so leave
                        // picking the title to the person registering.
                        document.getElementById('modal-tweet-author').textContent = (r.data.author_name || '') + (r.data.author_handle ? ' @' + r.data.author_handle : '');
                        document.getElementById('modal-tweet-text').textContent = r.data.text || '';
                        tweetPreview.hidden = !r.data.text;
                    })
                    .catch(function () { previewStatus.textContent = '投稿を取得できませんでした。'; hideTweetPreview(); });
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
            document.getElementById('modal-preview-status').hidden = true;
            document.getElementById('modal-tweet-preview').hidden = true;

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
