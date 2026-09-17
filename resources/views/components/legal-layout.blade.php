@props(['title'])
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | Channel Calendar</title>
    @vite(['resources/css/app.css'])
    <x-ga4 />
    <style>
        .legal { max-width: 46rem; margin: 0 auto; padding: 2.5rem 1rem 4rem; color: #1f2937; line-height: 1.8; }
        .legal h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem; }
        .legal .meta { font-size: 0.8125rem; color: #6b7280; margin-bottom: 2rem; }
        .legal h2 { font-size: 1.0625rem; font-weight: 700; color: #111827; margin: 2rem 0 0.5rem; padding-top: 0.25rem; border-top: 1px solid #e5e7eb; }
        .legal h3 { font-size: 0.9375rem; font-weight: 700; color: #374151; margin: 1.25rem 0 0.375rem; }
        .legal p, .legal li { font-size: 0.9375rem; }
        .legal ul { padding-left: 1.25rem; margin: 0.25rem 0 0.75rem; }
        .legal li { margin: 0.25rem 0; }
        .legal a { color: #2563eb; text-decoration: underline; }
        .legal .back { display: inline-block; margin-bottom: 1.5rem; font-size: 0.875rem; color: #6b7280; text-decoration: none; }
        .legal .back:hover { color: #111827; text-decoration: underline; }
        .legal nav.related { margin-top: 2.5rem; font-size: 0.875rem; color: #6b7280; }
    </style>
</head>
<body class="bg-gray-50">
    <main class="legal">
        <a href="{{ url('/') }}" class="back">← カレンダーへ戻る</a>
        {{ $slot }}
        <nav class="related">
            <a href="{{ url('/terms') }}">利用規約</a> ・ <a href="{{ url('/privacy') }}">プライバシーポリシー</a> ・ <a href="#" data-cc-reset>Cookie 設定</a>
        </nav>
    </main>
    <x-cookie-consent />
</body>
</html>
