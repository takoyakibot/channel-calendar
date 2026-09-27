<?php

namespace App\Http\Requests;

use App\Support\TwitchLogin;
use App\Support\XHandle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'short_name' => 'nullable|string|max:20',
            'x_search_keywords' => 'nullable|string|max:255',
            'twitch_login' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    try {
                        TwitchLogin::normalize($value);
                    } catch (\InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
            'x_handle' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    try {
                        XHandle::normalize($value);
                    } catch (\InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
        ];
    }

    /** The bare handle, or null when the field was left empty. */
    public function normalizedXHandle(): ?string
    {
        return $this->filled('x_handle') ? XHandle::normalize($this->input('x_handle')) : null;
    }

    /** The bare Twitch login, or null when the field was left empty. */
    public function normalizedTwitchLogin(): ?string
    {
        return $this->filled('twitch_login') ? TwitchLogin::normalize($this->input('twitch_login')) : null;
    }
}
