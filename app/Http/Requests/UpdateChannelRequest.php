<?php

namespace App\Http\Requests;

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
        ];
    }
}
