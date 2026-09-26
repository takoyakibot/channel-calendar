<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">切り抜き・出演の登録</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-4">誰でも URL から登録できる代わりに、削除はここからのみ行えます。誤登録や荒らしはこの一覧から消してください。</p>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2">公開日時</th>
                            <th class="text-left py-2 px-2">種別</th>
                            <th class="text-left py-2 px-2">動画</th>
                            <th class="text-left py-2 px-2">元チャンネル</th>
                            <th class="text-left py-2 px-2">メンバー</th>
                            <th class="text-left py-2 px-2">登録</th>
                            <th class="py-2 px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            <tr class="border-b hover:bg-gray-50 align-top">
                                <td class="py-2 px-2 whitespace-nowrap">{{ $post->published_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i') }}</td>
                                <td class="py-2 px-2 whitespace-nowrap">{{ \App\Models\VideoPost::KIND_LABELS[$post->kind] ?? $post->kind }}</td>
                                <td class="py-2 px-2 max-w-md">
                                    <a href="{{ $post->url() }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">{{ $post->title }}</a>
                                </td>
                                <td class="py-2 px-2">{{ $post->source_channel_name }}</td>
                                <td class="py-2 px-2">{{ $post->channels->pluck('name')->join(', ') }}</td>
                                <td class="py-2 px-2 text-xs text-gray-500 whitespace-nowrap">
                                    {{ $post->created_at->setTimezone('Asia/Tokyo')->format('m/d H:i') }}<br>
                                    {{ $post->submitter?->name ?? '匿名' }}
                                </td>
                                <td class="py-2 px-2 whitespace-nowrap">
                                    <form method="POST" action="{{ url('/admin/video-posts/' . $post->id) }}" onsubmit="return confirm('この登録を削除しますか？');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-xs">削除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-center text-gray-400">登録はまだありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $posts->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
