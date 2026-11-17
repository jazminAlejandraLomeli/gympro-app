<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    use SpanishMessages;

    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'max:200'],
        ];
    }
}
