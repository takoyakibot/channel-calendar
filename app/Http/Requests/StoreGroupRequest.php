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
        ];
    }
}
