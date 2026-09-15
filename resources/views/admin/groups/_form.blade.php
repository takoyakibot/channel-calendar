@if ($errors->any())
    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">グループ名</label>
    <input type="text" name="name" id="name" value="{{ old('name', $group?->name) }}"
           class="w-full border-gray-300 rounded-md shadow-sm" required>
</div>

<div class="mb-4">
    <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">親グループ</label>
    <select name="parent_id" id="parent_id" class="border-gray-300 rounded-md shadow-sm">
        <option value="">（なし・トップレベル）</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" {{ (string) old('parent_id', $group?->parent_id) === (string) $parent->id ? 'selected' : '' }}>
                /{{ $parent->path }} — {{ $parent->name }}
            </option>
        @endforeach
    </select>
    <p class="text-xs text-gray-500 mt-1">親を選ぶと URL は <code>/親/このスラッグ</code> になります。</p>
</div>

<div class="mb-4">
    <label for="thumbnail_url" class="block text-sm font-medium text-gray-700 mb-1">サムネイルURL</label>
    <input type="url" name="thumbnail_url" id="thumbnail_url" value="{{ old('thumbnail_url', $group?->thumbnail_url) }}"
           class="w-full border-gray-300 rounded-md shadow-sm" placeholder="https://...">
    <p class="text-xs text-gray-500 mt-1">トップページのカード表示に使用されます。空欄の場合はデフォルト表示になります。</p>
</div>

<div class="mb-6">
    <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">スラッグ（公開URL）</label>
    <input type="text" name="slug" id="slug" value="{{ old('slug', $group?->slug) }}"
           class="border-gray-300 rounded-md shadow-sm" pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?" maxlength="50" required
           placeholder="aaaa">
    <p class="text-xs text-gray-500 mt-1">半角英小文字・数字・ハイフン。同じ親の中で重複不可。</p>
</div>

<div class="mb-6">
    <p class="block text-sm font-medium text-gray-700 mb-2">直接所属するチャンネル</p>
    @if ($channels->isEmpty())
        <p class="text-sm text-gray-400">チャンネルがまだ登録されていません。</p>
    @else
        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
            @foreach ($channels as $channel)
                <label class="flex items-center gap-2 p-2 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="channels[]" value="{{ $channel->id }}" class="rounded border-gray-300"
                           {{ in_array($channel->id, old('channels', $selected ?? [])) ? 'checked' : '' }}>
                    @if ($channel->thumbnail_url)
                        <img src="{{ $channel->thumbnail_url }}" alt="" class="w-6 h-6 rounded-full">
                    @else
                        <span class="inline-block w-6 h-6 rounded-full" style="background-color:{{ $channel->color }}"></span>
                    @endif
                    <span class="text-sm truncate">{{ $channel->name }}</span>
                </label>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-2">配下のグループに所属するチャンネルは自動的に含まれるため、ここで選ぶ必要はありません。</p>
    @endif
</div>
