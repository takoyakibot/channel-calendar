<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャンネル追加</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ url('/admin/channels') }}">
                    @csrf
                    <div class="mb-4">
                        <label for="channel" class="block text-sm font-medium text-gray-700 mb-1">YouTube チャンネル</label>
                        <input type="text" name="channel" id="channel" value="{{ old('channel') }}"
                               class="w-full border-gray-300 rounded-md shadow-sm" placeholder="@handle" required autofocus>
                        <p class="text-xs text-gray-500 mt-1">
                            ハンドル（<code>@example</code>）、チャンネルURL（<code>https://www.youtube.com/@example</code>）、
                            チャンネルID（<code>UC...</code>）のいずれでも登録できます。名前とアイコンは自動取得します。
                        </p>
                    </div>
                    <div class="mb-6">
                        <label for="color" class="block text-sm font-medium text-gray-700 mb-1">カレンダー表示色</label>
                        <input type="color" name="color" id="color" value="{{ old('color', '#3B82F6') }}"
                               class="h-10 w-20 border-gray-300 rounded">
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">追加</button>
                        <a href="{{ url('/admin/channels') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
