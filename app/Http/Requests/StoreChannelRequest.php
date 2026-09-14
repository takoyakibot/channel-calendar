<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel_id' => 'required|string|max:255|unique:channels,channel_id',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }
}
