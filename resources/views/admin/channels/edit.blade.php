<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャンネル編集: {{ $channel->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="mb-6 flex items-center gap-4">
                    @if ($channel->thumbnail_url)
                        <img src="{{ $channel->thumbnail_url }}" alt="" class="w-16 h-16 rounded-full">
                    @endif
                    <div>
                        <p class="font-medium text-lg">{{ $channel->name }}</p>
                        <p class="text-sm text-gray-500">
                            @if ($channel->handle){{ $channel->handle }} · @endif<span class="font-mono">{{ $channel->channel_id }}</span>
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ url("/admin/channels/{$channel->id}") }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label for="color" class="block text-sm font-medium text-gray-700 mb-1">カレンダー表示色</label>
                        <input type="color" name="color" id="color" value="{{ old('color', $channel->color) }}"
                               class="h-10 w-20 border-gray-300 rounded">
                    </div>
                    <div class="mb-6">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $channel->is_active) ? 'checked' : '' }}
                                   class="rounded border-gray-300">
                            <span class="text-sm text-gray-700">有効</span>
                        </label>
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">更新</button>
                        <a href="{{ url('/admin/channels') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
