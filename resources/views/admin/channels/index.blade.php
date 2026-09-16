<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャンネル管理</h2>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500">
                    最終取得: {{ $lastFetchedAt ? $lastFetchedAt->format('Y/m/d H:i') . ' (JST)' : 'まだ取得していません' }}
                </span>
                <form method="POST" action="{{ url('/admin/streams/fetch') }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-900 text-sm"
                            onclick="this.disabled=true;this.textContent='取得中…';this.form.submit();">
                        今すぐ取得
                    </button>
                </form>
                <a href="{{ url('/admin/channels/create') }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    チャンネル追加
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">サムネイル</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">チャンネル名</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">カラー</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状態</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($channels as $channel)
                        <tr>
                            <td class="px-6 py-4">
                                @if ($channel->thumbnail_url)
                                    <img src="{{ $channel->thumbnail_url }}" alt="" class="w-10 h-10 rounded-full">
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $channel->name }}</div>
                                @if ($channel->handle)
                                    <a href="https://www.youtube.com/{{ $channel->handle }}" target="_blank" rel="noopener noreferrer"
                                       class="text-xs text-gray-500 hover:underline">{{ $channel->handle }}</a>
                                @else
                                    <div class="text-xs text-gray-400">{{ $channel->channel_id }}</div>
                                @endif
                                @if ($channel->x_handle)
                                    <a href="https://x.com/{{ $channel->x_handle }}" target="_blank" rel="noopener noreferrer"
                                       class="text-xs text-gray-500 hover:underline ml-2">𝕏 {{ '@' . $channel->x_handle }}</a>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-block w-6 h-6 rounded" style="background-color:{{ $channel->color }}"></span>
                                {{ $channel->color }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs {{ $channel->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $channel->is_active ? '有効' : '無効' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 space-x-2">
                                <a href="{{ url("/admin/channels/{$channel->id}/edit") }}" class="text-blue-600 hover:underline text-sm">編集</a>
                                <form method="POST" action="{{ url("/admin/channels/{$channel->id}") }}" style="display:inline;"
                                      onsubmit="return confirm('本当に削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">削除</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
