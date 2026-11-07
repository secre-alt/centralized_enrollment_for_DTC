<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(Auth::id()),
            ],
            // nullable — only present when the user actually picks a file
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => __('ui.validation.name_required'),
            'email.required' => __('ui.validation.email_required'),
            'email.email'    => __('ui.validation.email_invalid'),
            'email.unique'   => __('ui.validation.email_unique'),
            'avatar.image'   => __('ui.validation.avatar_invalid'),
            'avatar.mimes'   => __('ui.validation.avatar_formats'),
            'avatar.max'     => __('ui.validation.avatar_max'),
        ];
    }
}
