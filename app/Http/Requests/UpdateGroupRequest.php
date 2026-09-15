<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');
        $parentId = $this->input('parent_id') ?: null;

        return [
            'name' => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:groups,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($group) {
                    if ($value && in_array((int) $value, $group->subtreeIds(), true)) {
                        $fail('自分自身やその配下のグループを親にはできません。');
                    }
                },
            ],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^' . Group::SLUG_PATTERN . '$/',
                Rule::notIn(Group::RESERVED_SLUGS),
                Rule::unique('groups', 'slug')->where('parent_id', $parentId)->ignore($group->id),
            ],
            'thumbnail_url' => 'nullable|url|max:2048',
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
