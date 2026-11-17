<?php

namespace App\Http\Requests\Gym;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-gym');
    }

    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'brand_name' => ['required', 'string', 'max:80'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Usa un color en formato #RRGGBB.',
            'secondary_color.regex' => 'Usa un color en formato #RRGGBB.',
            'logo.max' => 'El logotipo debe pesar menos de 1 MB.',
        ];
    }
}
