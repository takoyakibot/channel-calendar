<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">グループ管理</h2>
            <a href="{{ url('/admin/groups/create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                グループ追加
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">グループ名</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">公開URL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">直接所属</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($groups as $group)
                        @php($depth = count($group->ancestors()))
                        <tr>
                            <td class="px-6 py-4 font-medium" style="padding-left: calc(1.5rem + {{ $depth }} * 1.25rem);">
                                @if ($depth > 0)<span class="text-gray-400 mr-1">└</span>@endif{{ $group->name }}
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ url('/' . $group->path) }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline text-sm">/{{ $group->path }}</a>
                            </td>
                            <td class="px-6 py-4">{{ $group->channels_count }}</td>
                            <td class="px-6 py-4 space-x-2">
                                <a href="{{ url("/admin/groups/{$group->id}/edit") }}" class="text-blue-600 hover:underline text-sm">編集</a>
                                <form method="POST" action="{{ url("/admin/groups/{$group->id}") }}" style="display:inline;"
                                      onsubmit="return confirm('本当に削除しますか？配下のグループも一緒に削除されます。')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">削除</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">グループはまだありません。</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">親グループのページには、配下のグループに所属するチャンネルもすべて表示されます。</p>
        </div>
    </div>
</x-app-layout>
