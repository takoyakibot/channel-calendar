<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $siteName = config('app.name', 'Channel Calendar');
        $pageDescription = $siteName . ' - 配信スケジュールをまとめてチェック';
    @endphp
    <title>{{ $siteName }}</title>
    <meta property="og:title" content="{{ $siteName }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $siteName }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    @vite(['resources/css/app.css'])
    <x-ga4 />
    <script>
    (function(){var c=document.documentElement.classList,t;try{t=localStorage.getItem('cc.theme')}catch(e){}
    if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme:dark)').matches))c.add('dark');})();
    </script>
    <style>
        :root {
            --cc-bg: #f9fafb; --cc-surface: #fff; --cc-surface-alt: #f3f4f6;
            --cc-text: #111827; --cc-text-secondary: #374151;
            --cc-text-tertiary: #6b7280; --cc-text-muted: #9ca3af;
            --cc-border: #e5e7eb; --cc-border-light: #d1d5db;
            --cc-active-bg: #111827; --cc-active-text: #fff;
        }
        .dark {
            --cc-bg: #0c0c0e; --cc-surface: #1a1a1c; --cc-surface-alt: #27272a;
            --cc-text: #f4f4f5; --cc-text-secondary: #a1a1aa;
            --cc-text-tertiary: #71717a; --cc-text-muted: #52525b;
            --cc-border: #27272a; --cc-border-light: #3f3f46;
            --cc-active-bg: #f4f4f5; --cc-active-text: #18181b;
        }
        .landing-header { text-align: center; margin-bottom: 2.5rem; }
        .landing-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--cc-text); margin: 0 0 0.25rem; }
        .landing-header p { color: var(--cc-text-tertiary); font-size: 0.9375rem; margin: 0; }

        .group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        @media (max-width: 640px) { .group-grid { grid-template-columns: 1fr; } }

        .group-card { background: var(--cc-surface); border: 1px solid var(--cc-border); border-radius: 0.75rem; overflow: hidden; text-decoration: none; color: inherit; transition: box-shadow 0.15s, border-color 0.15s; display: flex; flex-direction: column; }
        .group-card:hover { border-color: var(--cc-border-light); box-shadow: 0 4px 12px rgb(0 0 0 / 0.08); }
        .group-thumb { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; background: #f3f4f6; }
        .group-thumb-placeholder { width: 100%; aspect-ratio: 16 / 9; background: linear-gradient(135deg, var(--cc-border) 0%, var(--cc-bg) 100%); display: flex; align-items: center; justify-content: center; color: var(--cc-text-muted); font-size: 2rem; font-weight: 700; }
        .group-body { padding: 1rem 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .group-name { font-size: 1.125rem; font-weight: 700; color: var(--cc-text); margin: 0 0 0.5rem; }
        .channel-avatars { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: auto; }
        .channel-avatars img { width: 1.75rem; height: 1.75rem; border-radius: 50%; object-fit: cover; border: 2px solid var(--cc-surface); box-shadow: 0 0 0 1px var(--cc-border); }
        .channel-avatars .fallback { width: 1.75rem; height: 1.75rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.6875rem; border: 2px solid var(--cc-surface); box-shadow: 0 0 0 1px var(--cc-border); }
        .channel-count { font-size: 0.75rem; color: var(--cc-text-tertiary); margin-top: 0.5rem; }

        .site-footer { margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid var(--cc-border); display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; font-size: 0.75rem; color: var(--cc-text-tertiary); }
        .site-footer nav { display: flex; gap: 1rem; flex: none; }
        .site-footer a { color: var(--cc-text-tertiary); text-decoration: none; }
        .site-footer a:hover { color: var(--cc-text); text-decoration: underline; }
    </style>
</head>
<body style="background:var(--cc-bg);color:var(--cc-text)">
    <div class="max-w-screen-xl mx-auto px-4 py-8">
        <header class="landing-header">
            <h1>{{ $siteName }}</h1>
            <p>配信スケジュールをまとめてチェック</p>
        </header>

        @if ($groups->isEmpty())
            <p class="text-center text-gray-400 dark:text-zinc-500">グループがまだ登録されていません。</p>
        @else
            <div class="group-grid">
                @foreach ($groups as $group)
                    <a href="{{ url('/' . $group->path) }}" class="group-card">
                        @if ($group->thumbnail_url)
                            <img src="{{ $group->thumbnail_url }}" alt="{{ $group->name }}" class="group-thumb" loading="lazy">
                        @else
                            <div class="group-thumb-placeholder">{{ mb_substr($group->name, 0, 1) }}</div>
                        @endif
                        <div class="group-body">
                            <h2 class="group-name">{{ $group->name }}</h2>
                            @if ($group->channels->isNotEmpty())
                                <div class="channel-avatars">
                                    @foreach ($group->channels->take(10) as $channel)
                                        @if ($channel->thumbnail_url)
                                            <img src="{{ $channel->thumbnail_url }}" alt="{{ $channel->name }}" title="{{ $channel->name }}" loading="lazy">
                                        @else
                                            <span class="fallback" style="background-color: {{ $channel->color ?? '#9ca3af' }}" title="{{ $channel->name }}">{{ mb_substr($channel->name, 0, 1) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                <p class="channel-count">{{ $group->channels_count }}チャンネル</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-6 text-center" style="display: flex; justify-content: center; gap: 1rem; align-items: center;">
            @guest
                <a href="{{ route('auth.google') }}" data-cc-login class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-lg text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700 shadow-sm">
                    Googleでログイン
                </a>
            @else
                <span class="text-sm text-gray-600 dark:text-zinc-400">{{ Auth::user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200 underline">ログアウト</button>
                </form>
                @if (Auth::user()->is_admin)
                    <a href="{{ url('/admin/channels') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">管理画面</a>
                @endif
            @endguest
        </div>

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
    <x-cookie-consent />
</body>
</html>
