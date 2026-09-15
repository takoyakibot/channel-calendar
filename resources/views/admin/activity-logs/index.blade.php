<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">操作ログ</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2">日時</th>
                            <th class="text-left py-2 px-2">ユーザー</th>
                            <th class="text-left py-2 px-2">操作</th>
                            <th class="text-left py-2 px-2">詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-2 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td class="py-2 px-2">{{ $log->user?->name ?? '(deleted)' }}</td>
                                <td class="py-2 px-2">{{ $log->action }}</td>
                                <td class="py-2 px-2 text-xs text-gray-500 max-w-xs truncate" title="{{ $log->payload ? json_encode($log->payload, JSON_UNESCAPED_UNICODE) : '' }}">{{ $log->payload ? \Illuminate\Support\Str::limit(json_encode($log->payload, JSON_UNESCAPED_UNICODE), 120) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-400">ログがありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
