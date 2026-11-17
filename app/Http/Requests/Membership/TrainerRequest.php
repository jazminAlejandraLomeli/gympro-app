<?php

namespace App\Http\Requests\Membership;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta y edición de entrenadores (la contraseña es opcional al editar). */
class TrainerRequest extends FormRequest
{
    use SpanishMessages;

    public function authorize(): bool
    {
        return $this->user()->can('manage-gym');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $trainer = $this->route('trainer');
        $unique = Rule::unique('users', 'email')->where('gym_id', app(TenantContext::class)->id());

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', $trainer ? $unique->ignore($trainer->id) : $unique],
            'password' => [$trainer ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
        ];
    }
}
