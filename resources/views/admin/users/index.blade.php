<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ユーザー管理</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2">名前</th>
                            <th class="text-left py-2 px-2">メール</th>
                            <th class="text-left py-2 px-2">登録日</th>
                            <th class="text-left py-2 px-2">状態</th>
                            <th class="text-left py-2 px-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b hover:bg-gray-50 {{ $user->is_banned ? 'bg-red-50' : '' }}">
                                <td class="py-2 px-2">{{ $user->name }}</td>
                                <td class="py-2 px-2">{{ $user->email }}</td>
                                <td class="py-2 px-2 whitespace-nowrap">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="py-2 px-2">
                                    @if ($user->is_admin)
                                        <span class="text-blue-600 font-semibold">管理者</span>
                                    @elseif ($user->is_banned)
                                        <span class="text-red-600 font-semibold">BAN</span>
                                    @else
                                        <span class="text-green-600">有効</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2">
                                    @unless ($user->is_admin)
                                        <form method="POST" action="{{ url("/admin/users/{$user->id}/toggle-ban") }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-xs {{ $user->is_banned ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800' }} underline">
                                                {{ $user->is_banned ? 'BAN解除' : 'BAN' }}
                                            </button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
