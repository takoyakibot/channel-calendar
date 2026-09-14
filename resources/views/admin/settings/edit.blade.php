<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">設定</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-4 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-4 bg-red-100 text-red-700 rounded">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">YouTube API キー</h3>
                <p class="text-sm text-gray-500 mb-4">
                    配信予定の取得とチャンネル情報の自動取得に使います。
                    現在の状態:
                    @if ($keySource === 'database')
                        <span class="font-medium text-green-700">設定済み（管理画面） {{ $maskedKey }}</span>
                    @elseif ($keySource === 'env')
                        <span class="font-medium text-blue-700">設定済み（サーバーの .env） {{ $maskedKey }}</span>
                    @else
                        <span class="font-medium text-red-700">未設定</span>
                    @endif
                </p>

                <form method="POST" action="{{ url('/admin/settings') }}" class="mb-6">
                    @csrf
                    @method('PUT')
                    <label for="youtube_api_key" class="block text-sm font-medium text-gray-700 mb-1">API キー</label>
                    <input type="password" name="youtube_api_key" id="youtube_api_key" value=""
                           class="w-full border-gray-300 rounded-md shadow-sm font-mono" autocomplete="off"
                           placeholder="{{ $keySource === 'database' ? '変更する場合のみ入力（空欄で保存すると .env の値に戻ります）' : 'AIza...' }}">
                    <p class="text-xs text-gray-500 mt-1">
                        Google Cloud Console で YouTube Data API v3 を有効化して作成したキーを貼り付けてください。値は暗号化して保存されます。
                    </p>
                    <div class="mt-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">保存</button>
                    </div>
                </form>

                <form method="POST" action="{{ url('/admin/settings/test') }}" class="border-t border-gray-200 pt-4">
                    @csrf
                    <p class="text-sm text-gray-600 mb-2">現在有効なキーで YouTube API に1回アクセスして疎通を確認します。</p>
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-900"
                            {{ $keySource === 'none' ? 'disabled' : '' }}>接続テスト</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
