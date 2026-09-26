<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">タグ管理（ゲーム・カテゴリ）</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
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
                <h3 class="font-semibold mb-4">タグ（{{ $tags->count() }}）</h3>
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
                            <tr class="border-b align-top hover:bg-gray-50">
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
</x-app-layout>
