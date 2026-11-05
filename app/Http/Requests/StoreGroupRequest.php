<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parentId = $this->input('parent_id') ?: null;

        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:groups,id',
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^' . Group::SLUG_PATTERN . '$/',
                Rule::notIn(Group::RESERVED_SLUGS),
                Rule::unique('groups', 'slug')->where('parent_id', $parentId),
            ],
            'thumbnail_url' => 'nullable|url|max:2048',
            // Spelled out rather than `image`, which lets SVG (scriptable) through in Laravel 10.
            'thumbnail_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:2048|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            'channels' => 'nullable|array',
            'channels.*' => 'integer|exists:channels,id',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'スラッグは半角英小文字・数字・ハイフンのみ使用できます。',
            'slug.not_in' => 'このスラッグはシステムで予約されているため使用できません。',
            'slug.unique' => '同じ親の中に同じスラッグのグループが既にあります。',
            'thumbnail_file.mimes' => 'サムネイル画像は JPEG / PNG / WebP / GIF のいずれかにしてください。',
            'thumbnail_file.max' => 'サムネイル画像は 2MB 以下にしてください。',
            'thumbnail_file.dimensions' => 'サムネイル画像は縦横 100〜4000px にしてください。',
        ];
    }
}
