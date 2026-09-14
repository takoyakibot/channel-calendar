<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">グループ編集: {{ $group->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ url("/admin/groups/{$group->id}") }}">
                    @csrf
                    @method('PUT')
                    @include('admin.groups._form')
                    <div class="flex gap-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">更新</button>
                        <a href="{{ url('/admin/groups') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
