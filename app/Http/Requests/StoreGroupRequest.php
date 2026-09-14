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
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(Group::RESERVED_SLUGS),
                'unique:groups,slug',
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
        ];
    }
}
