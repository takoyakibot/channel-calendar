<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">タグ管理（ゲーム・カテゴリ）</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">
            {{-- Every action is a form + redirect; a toast and a restored scroll position
                 keep the flow going when classifying many terms in a row. --}}
            @php($isError = session('error') || $errors->any())
            @php($toast = session('success') ?? session('error') ?? ($errors->any() ? $errors->first() : null))
            @if ($toast)
                <div id="admin-toast" role="status" aria-live="polite"
                     class="fixed bottom-6 right-6 z-50 max-w-md px-4 py-3 rounded-lg shadow-lg text-sm text-white {{ $isError ? 'bg-red-600' : 'bg-gray-900' }}">
                    {{ $toast }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-1">未分類の語句（{{ $unmatched->count() }}）</h3>
                <p class="text-sm text-gray-500 mb-4">配信タイトルの【…】から拾った語です。分類すると、その語を含む配信すべてに自動でタグが付きます。</p>
                @forelse ($unmatched as $term)
                    <form method="POST" action="{{ url('/admin/terms/classify') }}" class="flex flex-wrap items-center gap-2 text-sm border-b py-2">
                        @csrf
                        <input type="hidden" name="term" value="{{ $term->display }}">
                        <span class="font-medium min-w-[10rem]">{{ $term->display }} <span class="text-xs text-gray-400">×{{ $term->count }}</span></span>
                        <select name="kind" class="border-gray-300 rounded text-sm">
                            <option value="game">ゲーム</option>
                            <option value="category">カテゴリ</option>
                            <option value="ignore">無視</option>
                        </select>
                        <select name="tag_id" class="border-gray-300 rounded text-sm">
                            <option value="">新しいタグとして作成</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}">既存: {{ $tag->name }}（{{ \App\Models\Tag::KIND_LABELS[$tag->kind] }}）</option>
                            @endforeach
                        </select>
                        <input type="text" name="name" placeholder="新規タグの表示名（省略時は語句のまま）" maxlength="60" class="border-gray-300 rounded text-sm w-64">
                        <button type="submit" class="px-3 py-1 bg-gray-900 text-white rounded text-xs">分類</button>
                    </form>
                @empty
                    <p class="text-sm text-gray-400">未分類の語句はありません。</p>
                @endforelse
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h3 class="font-semibold">タグ（<span id="tag-search-count">{{ $tags->count() }}</span>）</h3>
                    <div class="flex items-center gap-2">
                        <input type="search" id="tag-search" placeholder="名前・エイリアス・種別で検索" autocomplete="off"
                               aria-label="タグを検索" class="border-gray-300 rounded text-sm w-64">
                        <button type="button" id="tag-search-clear" class="px-3 py-1 border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-50">クリア</button>
                    </div>
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2">名前 / 種別</th>
                            <th class="text-left py-2 px-2">エイリアス（表記ゆれ）</th>
                            <th class="text-left py-2 px-2">配信数</th>
                            <th class="text-left py-2 px-2">統合 / 削除</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tags as $tag)
                            <tr class="border-b align-top hover:bg-gray-50" data-tag-row
                                data-search="{{ implode(' ', array_merge([$tag->name, \App\Models\Tag::KIND_LABELS[$tag->kind]], $tag->aliases->pluck('alias')->all())) }}">
                                <td class="py-2 px-2">
                                    <form method="POST" action="{{ url('/admin/tags/' . $tag->id) }}" class="flex items-center gap-1">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $tag->name }}" maxlength="60" class="border-gray-300 rounded text-sm w-40">
                                        <select name="kind" class="border-gray-300 rounded text-sm">
                                            @foreach (\App\Models\Tag::KINDS as $kind)
                                                <option value="{{ $kind }}" @selected($tag->kind === $kind)>{{ \App\Models\Tag::KIND_LABELS[$kind] }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="text-xs text-blue-600 hover:underline">更新</button>
                                    </form>
                                </td>
                                <td class="py-2 px-2">
                                    <div class="flex flex-wrap gap-1 mb-1">
                                        @foreach ($tag->aliases as $alias)
                                            <form method="POST" action="{{ url('/admin/tag-aliases/' . $alias->id) }}" class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 rounded-full text-xs">
                                                @csrf
                                                @method('DELETE')
                                                <span>{{ $alias->alias }}</span>
                                                <button type="submit" class="text-gray-400 hover:text-red-600" title="このエイリアスを削除">×</button>
                                            </form>
                                        @endforeach
                                    </div>
                                    <form method="POST" action="{{ url('/admin/tags/' . $tag->id . '/aliases') }}" class="flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="alias" placeholder="例: マイクラ" maxlength="80" class="border-gray-300 rounded text-xs w-32">
                                        <button type="submit" class="text-xs text-blue-600 hover:underline">追加</button>
                                    </form>
                                </td>
                                <td class="py-2 px-2 whitespace-nowrap">{{ $tag->streams_count }}</td>
                                <td class="py-2 px-2">
                                    <form method="POST" action="{{ url('/admin/tags/' . $tag->id . '/merge') }}" class="flex items-center gap-1 mb-1" onsubmit="return confirm('このタグを統合先に吸収させます。よろしいですか？');">
                                        @csrf
                                        <select name="into_tag_id" class="border-gray-300 rounded text-xs">
                                            @foreach ($tags as $other)
                                                @continue($other->id === $tag->id)
                                                <option value="{{ $other->id }}">{{ $other->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="text-xs text-blue-600 hover:underline">に統合</button>
                                    </form>
                                    <form method="POST" action="{{ url('/admin/tags/' . $tag->id) }}" onsubmit="return confirm('タグを削除しますか？付いている配信からも外れます。');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">削除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-400">タグはまだありません。</td></tr>
                        @endforelse
                        <tr id="tag-search-empty" hidden><td colspan="4" class="py-4 text-center text-gray-400">一致するタグはありません。</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">無視した語句（{{ $ignored->count() }}）</h3>
                <div class="flex flex-wrap gap-2">
                    @forelse ($ignored as $term)
                        <form method="POST" action="{{ url('/admin/ignored-terms/' . $term->id) }}" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 rounded-full text-xs">
                            @csrf
                            @method('DELETE')
                            <span>{{ $term->display }}</span>
                            <button type="submit" class="text-gray-400 hover:text-blue-600" title="未分類に戻す">↩</button>
                        </form>
                    @empty
                        <p class="text-sm text-gray-400">ありません。</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        // Tag search: filters the table as you type. The query is kept in
        // sessionStorage so it survives reloads and the redirect after every form,
        // and it is applied before the scroll restore below so the page height matches.
        var SEARCH_KEY = 'admin.tags.search';
        var input = document.getElementById('tag-search');
        var clear = document.getElementById('tag-search-clear');
        var count = document.getElementById('tag-search-count');
        var empty = document.getElementById('tag-search-empty');
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-tag-row]'));
        var norm = function (s) { return String(s).normalize('NFKC').toLowerCase(); };
        rows.forEach(function (row) { row._search = norm(row.dataset.search || ''); });

        function applySearch() {
            var words = norm(input.value).split(/\s+/).filter(Boolean);
            var shown = 0;
            rows.forEach(function (row) {
                var hit = words.every(function (w) { return row._search.indexOf(w) !== -1; });
                row.hidden = !hit;
                if (hit) shown++;
            });
            count.textContent = words.length ? shown + ' / ' + rows.length : String(rows.length);
            empty.hidden = !(rows.length && words.length && shown === 0);
            clear.disabled = !input.value;
            try {
                if (input.value) sessionStorage.setItem(SEARCH_KEY, input.value);
                else sessionStorage.removeItem(SEARCH_KEY);
            } catch (e) {}
        }

        try { input.value = sessionStorage.getItem(SEARCH_KEY) || ''; } catch (e) {}
        input.addEventListener('input', applySearch);
        clear.addEventListener('click', function () {
            input.value = '';
            applySearch();
            input.focus();
        });
        applySearch();

        // Remember where the page was scrolled when a form is sent, and come back
        // there after the redirect so the next term is right under the cursor.
        var KEY = 'admin.tags.scrollY';
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                try { sessionStorage.setItem(KEY, String(window.scrollY)); } catch (e) {}
            });
        });
        try {
            var y = sessionStorage.getItem(KEY);
            if (y !== null) {
                sessionStorage.removeItem(KEY);
                window.scrollTo(0, Number(y));
            }
        } catch (e) {}

        var toast = document.getElementById('admin-toast');
        if (toast) {
            setTimeout(function () {
                toast.style.transition = 'opacity 0.3s';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 300);
            }, 4000);
        }
    })();
    </script>
</x-app-layout>
